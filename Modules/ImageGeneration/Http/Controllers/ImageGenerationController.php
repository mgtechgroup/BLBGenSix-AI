<?php

namespace Modules\ImageGeneration\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Generation;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Modules\ImageGeneration\Services\ImageGenerationService;

class ImageGenerationController extends Controller
{
    protected ImageGenerationService $generationService;

    public function __construct(ImageGenerationService $generationService)
    {
        $this->generationService = $generationService;
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'prompt' => 'required|string|max:2000',
            'negative_prompt' => 'nullable|string|max:2000',
            'width' => 'integer|min:256|max:2048|default:1024',
            'height' => 'integer|min:256|max:2048|default:1024',
            'steps' => 'integer|min:10|max:100|default:30',
            'cfg_scale' => 'numeric|min:1|max:20|default:7.5',
            'style' => 'string|in:anime,realistic,semi-realistic,artistic,cartoon|default:realistic',
            'seed' => 'nullable|integer',
            'batch_size' => 'integer|min:1|max:8|default:1',
            'sampler' => 'string|default:DPM++ 2M Karras',
            'model' => 'string|default:stable-diffusion-xl',
        ]);

        $generation = Generation::create([
            'user_id' => auth()->id(),
            'type' => Generation::TYPE_IMAGE,
            'model_used' => $validated['model'],
            'prompt' => $validated['prompt'],
            'negative_prompt' => $validated['negative_prompt'] ?? null,
            'parameters' => $validated,
            'status' => 'queued',
        ]);

        // Dispatch to queue
        $this->generationService->generateImage($generation);

        return response()->json([
            'success' => true,
            'generation_id' => $generation->id,
            'status' => 'queued',
            'message' => 'Image generation started',
        ]);
    }

    public function batchGenerate(Request $request)
    {
        $validated = $request->validate([
            'prompts' => 'required|array|min:1|max:20',
            'prompts.*' => 'required|string|max:2000',
            'settings' => 'array',
        ]);

        $generations = [];
        
        foreach ($validated['prompts'] as $prompt) {
            $generation = Generation::create([
                'user_id' => auth()->id(),
                'type' => Generation::TYPE_IMAGE,
                'model_used' => $validated['settings']['model'] ?? 'stable-diffusion-xl',
                'prompt' => $prompt,
                'parameters' => $validated['settings'] ?? [],
                'status' => 'queued',
            ]);

            $this->generationService->generateImage($generation);
            $generations[] = $generation->id;
        }

        return response()->json([
            'success' => true,
            'generation_ids' => $generations,
            'count' => count($generations),
        ]);
    }

    public function imageToImage(Request $request)
    {
        $validated = $request->validate([
            'image' => 'required|file|mimes:png,jpg,jpeg,webp|max:10240',
            'prompt' => 'required|string|max:2000',
            'strength' => 'numeric|min:0|max:1|default:0.75',
            'steps' => 'integer|min:10|max:100|default:30',
        ]);

        $imagePath = $request->file('image')->store('temp');

        $generation = Generation::create([
            'user_id' => auth()->id(),
            'type' => Generation::TYPE_IMAGE,
            'model_used' => 'img2img',
            'prompt' => $validated['prompt'],
            'parameters' => [
                'source_image' => $imagePath,
                'strength' => $validated['strength'],
                'steps' => $validated['steps'],
            ],
            'status' => 'queued',
        ]);

        $this->generationService->imageToImage($generation);

        return response()->json([
            'success' => true,
            'generation_id' => $generation->id,
        ]);
    }

    public function upscale(Request $request)
    {
        $validated = $request->validate([
            'image_id' => 'required|uuid|exists:generations,id',
            'scale_factor' => 'integer|min:2|max:4|default:2',
        ]);

        $original = Generation::findOrFail($validated['image_id']);

        $generation = Generation::create([
            'user_id' => auth()->id(),
            'type' => Generation::TYPE_IMAGE,
            'model_used' => 'esrgan-upscaler',
            'prompt' => 'upscale',
            'parameters' => [
                'source_generation_id' => $original->id,
                'scale_factor' => $validated['scale_factor'],
            ],
            'status' => 'queued',
        ]);

        $this->generationService->upscale($generation);

        return response()->json([
            'success' => true,
            'generation_id' => $generation->id,
        ]);
    }

    public function inpaint(Request $request)
    {
        $validated = $request->validate([
            'image_id' => 'required|uuid|exists:generations,id',
            'mask' => 'required|file|mimes:png',
            'prompt' => 'required|string|max:2000',
        ]);

        $original = Generation::findOrFail($validated['image_id']);
        $maskPath = $request->file('mask')->store('temp');

        $generation = Generation::create([
            'user_id' => auth()->id(),
            'type' => Generation::TYPE_IMAGE,
            'model_used' => 'inpainting',
            'prompt' => $validated['prompt'],
            'parameters' => [
                'source_generation_id' => $original->id,
                'mask_path' => $maskPath,
            ],
            'status' => 'queued',
        ]);

        $this->generationService->inpaint($generation);

        return response()->json([
            'success' => true,
            'generation_id' => $generation->id,
        ]);
    }

    public function variation(Request $request)
    {
        $validated = $request->validate([
            'image_id' => 'required|uuid|exists:generations,id',
            'variation_strength' => 'numeric|min:0|max:1|default:0.3',
        ]);

        $original = Generation::findOrFail($validated['image_id']);

        $generation = Generation::create([
            'user_id' => auth()->id(),
            'type' => Generation::TYPE_IMAGE,
            'model_used' => 'variation',
            'prompt' => $original->prompt,
            'parameters' => [
                'source_generation_id' => $original->id,
                'variation_strength' => $validated['variation_strength'],
            ],
            'status' => 'queued',
        ]);

        $this->generationService->variation($generation);

        return response()->json([
            'success' => true,
            'generation_id' => $generation->id,
        ]);
    }

    public function styles()
    {
        return response()->json([
            'styles' => [
                ['id' => 'anime', 'name' => 'Anime', 'description' => 'Japanese animation style'],
                ['id' => 'realistic', 'name' => 'Realistic', 'description' => 'Photorealistic generation'],
                ['id' => 'semi-realistic', 'name' => 'Semi-Realistic', 'description' => 'Digital art style'],
                ['id' => 'artistic', 'name' => 'Artistic', 'description' => 'Painterly and artistic'],
                ['id' => 'cartoon', 'name' => 'Cartoon', 'description' => 'Bold cartoon style'],
            ]
        ]);
    }

    public function models()
    {
        return response()->json([
            'models' => [
                ['id' => 'sdxl-base', 'name' => 'Stable Diffusion XL', 'resolution' => '1024x1024'],
                ['id' => 'sdxl-nsfw', 'name' => 'SDXL Uncensored', 'resolution' => '1024x1024'],
                ['id' => 'anime-v3', 'name' => 'Anime Model V3', 'resolution' => '768x768'],
                ['id' => 'realistic-vision', 'name' => 'Realistic Vision', 'resolution' => '1024x1024'],
            ]
        ]);
    }

    public function history(Request $request)
    {
        $generations = auth()->user()
            ->generations()
            ->byType(Generation::TYPE_IMAGE)
            ->latest()
            ->paginate(20);

        return response()->json($generations);
    }

    public function show($id)
    {
        $generation = Generation::where('user_id', auth()->id())
            ->findOrFail($id);

        return response()->json($generation);
    }

    public function destroy($id)
    {
        $generation = Generation::where('user_id', auth()->id())
            ->findOrFail($id);

        // Delete output files
        if ($generation->output_files) {
            foreach ($generation->output_files as $file) {
                Storage::delete($file);
            }
        }

        $generation->delete();

        return response()->json(['success' => true, 'message' => 'Generation deleted']);
    }
}
