<?php

namespace Modules\TextGeneration\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Generation;
use Illuminate\Http\Request;
use Modules\TextGeneration\Services\TextGenerationService;

class TextGenerationController extends Controller
{
    protected TextGenerationService $textService;

    public function __construct(TextGenerationService $textService)
    {
        $this->textService = $textService;
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'prompt' => 'required|string|max:5000',
            'max_tokens' => 'integer|min:1000|max:100000|default:10000',
            'temperature' => 'numeric|min:0|max:1|default:0.9',
            'style' => 'string|in:erotica,romance,fantasy,sci-fi,anime,bdsm,taboo|default:erotica',
            'format' => 'string|in:short_story,chapter,poem,script|default:short_story',
            'characters' => 'nullable|array',
            'setting' => 'nullable|string|max:5000',
            'tone' => 'nullable|string',
        ]);

        $generation = Generation::create([
            'user_id' => auth()->id(),
            'type' => Generation::TYPE_TEXT,
            'model_used' => 'gpt-4-uncensored',
            'prompt' => $validated['prompt'],
            'parameters' => $validated,
            'status' => 'processing',
        ]);

        $result = $this->textService->generateText($generation);

        return response()->json([
            'success' => true,
            'generation_id' => $generation->id,
            'word_count' => $result['word_count'],
            'output_url' => $result['output_url'],
        ]);
    }

    public function generateNovel(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:500',
            'genre' => 'required|string',
            'synopsis' => 'required|string|max:10000',
            'target_length' => 'integer|min:10000|max:500000|default:50000',
            'characters' => 'required|array|min:1',
            'characters.*.name' => 'required|string',
            'characters.*.description' => 'required|string',
            'outline' => 'nullable|array',
            'style' => 'string|default:erotica',
        ]);

        // Generate outline first
        $outline = $validated['outline'] ?? $this->textService->generateOutline(
            $validated['synopsis'],
            $validated['characters'],
            $validated['target_length']
        );

        $generation = Generation::create([
            'user_id' => auth()->id(),
            'type' => Generation::TYPE_TEXT,
            'model_used' => 'novel-generator',
            'prompt' => json_encode($validated),
            'parameters' => array_merge($validated, ['outline' => $outline]),
            'status' => 'queued',
        ]);

        $this->textService->generateNovel($generation);

        return response()->json([
            'success' => true,
            'generation_id' => $generation->id,
            'chapter_count' => count($outline),
            'estimated_words' => $validated['target_length'],
        ]);
    }

    public function novelOutline(Request $request)
    {
        $validated = $request->validate([
            'synopsis' => 'required|string|max:10000',
            'genre' => 'required|string',
            'characters' => 'required|array',
            'target_length' => 'integer|min:10000|max:500000|default:50000',
        ]);

        $outline = $this->textService->generateOutline(
            $validated['synopsis'],
            $validated['characters'],
            $validated['target_length']
        );

        return response()->json([
            'success' => true,
            'outline' => $outline,
            'chapter_count' => count($outline),
        ]);
    }

    public function generateStoryboard(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:500',
            'genre' => 'required|string',
            'num_scenes' => 'integer|min:5|max:50|default:10',
            'characters' => 'required|array',
            'synopsis' => 'required|string|max:10000',
        ]);

        $result = $this->textService->generateStoryboard($validated);

        $generation = Generation::create([
            'user_id' => auth()->id(),
            'type' => Generation::TYPE_TEXT,
            'model_used' => 'storyboard-generator',
            'prompt' => json_encode($validated),
            'parameters' => $validated,
            'status' => 'completed',
            'output_url' => $result['output_url'],
        ]);

        return response()->json([
            'success' => true,
            'generation_id' => $generation->id,
            'scenes' => $result['scenes'],
            'output_url' => $result['output_url'],
        ]);
    }

    public function generateScript(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:500',
            'genre' => 'required|string',
            'characters' => 'required|array',
            'synopsis' => 'required|string|max:10000',
            'format' => 'string|in:screenplay,stage,video_script|default:screenplay',
        ]);

        $result = $this->textService->generateScript($validated);

        return response()->json([
            'success' => true,
            'output_url' => $result['output_url'],
            'word_count' => $result['word_count'],
        ]);
    }

    public function characterSheet(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'description' => 'required|string|max:5000',
            'style' => 'string|default:realistic',
            'detail_level' => 'string|in:basic,detailed,comprehensive|default:detailed',
        ]);

        $result = $this->textService->generateCharacterSheet($validated);

        return response()->json([
            'success' => true,
            'character' => $result['character'],
        ]);
    }

    public function worldBuilding(Request $request)
    {
        $validated = $request->validate([
            'concept' => 'required|string|max:5000',
            'genre' => 'required|string',
            'detail_level' => 'string|default:comprehensive',
        ]);

        $result = $this->textService->generateWorldBuilding($validated);

        return response()->json([
            'success' => true,
            'world' => $result['world'],
        ]);
    }

    public function continue(Request $request)
    {
        $validated = $request->validate([
            'text_id' => 'required|uuid|exists:generations,id',
            'continuation_prompt' => 'nullable|string|max:5000',
            'additional_tokens' => 'integer|min:1000|max:50000|default:5000',
        ]);

        $original = Generation::findOrFail($validated['text_id']);

        $result = $this->textService->continueText($original, $validated);

        return response()->json([
            'success' => true,
            'generation_id' => $result['generation_id'],
            'word_count' => $result['word_count'],
        ]);
    }

    public function genres()
    {
        return response()->json([
            'genres' => [
                'erotica', 'romance', 'fantasy', 'sci-fi', 'anime',
                'bdsm', 'taboo', 'harem', 'yaoi', 'yuri',
                'monster', 'supernatural', 'historical', 'contemporary',
            ]
        ]);
    }

    public function templates()
    {
        return response()->json([
            'templates' => [
                ['id' => 'novel-basic', 'name' => 'Basic Novel', 'type' => 'novel'],
                ['id' => 'novel-serialized', 'name' => 'Serialized Novel', 'type' => 'novel'],
                ['id' => 'storyboard-video', 'name' => 'Video Storyboard', 'type' => 'storyboard'],
                ['id' => 'script-screenplay', 'name' => 'Screenplay', 'type' => 'script'],
                ['id' => 'short-story', 'name' => 'Short Story', 'type' => 'story'],
            ]
        ]);
    }

    public function history()
    {
        return response()->json(
            auth()->user()
                ->generations()
                ->byType(Generation::TYPE_TEXT)
                ->latest()
                ->paginate(20)
        );
    }

    public function show($id)
    {
        return response()->json(
            Generation::where('user_id', auth()->id())->findOrFail($id)
        );
    }

    public function update(Request $request, $id)
    {
        $generation = Generation::where('user_id', auth()->id())->findOrFail($id);
        
        $validated = $request->validate([
            'title' => 'nullable|string|max:500',
            'content' => 'nullable|string',
        ]);

        $generation->update($validated);

        return response()->json(['success' => true, 'generation' => $generation]);
    }

    public function destroy($id)
    {
        Generation::where('user_id', auth()->id())->findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }
}
