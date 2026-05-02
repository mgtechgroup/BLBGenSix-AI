<?php

namespace Modules\TextGeneration\Services;

use App\Models\Generation;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenAI;
use Log;

class TextGenerationService
{
    protected $client;

    public function __construct()
    {
        $this->client = OpenAI::client(config('services.openai.api_key'));
    }

    public function generateText(Generation $generation): array
    {
        try {
            $params = $generation->parameters;
            $prompt = $this->buildTextPrompt($params);

            $response = $this->client->chat()->create([
                'model' => 'gpt-4',
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'temperature' => $params['temperature'] ?? 0.9,
                'max_tokens' => min($params['max_tokens'] ?? 10000, 100000),
            ]);

            $content = $response->choices[0]->message->content;

            return $this->saveAndReturn($generation, $content);

        } catch (\Exception $e) {
            $generation->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            Log::error('Text generation failed', ['id' => $generation->id]);
            throw $e;
        }
    }

    public function generateNovel(Generation $generation): void
    {
        $generation->update(['status' => 'processing']);

        $params = $generation->parameters;
        $outline = $params['outline'];
        $fullNovel = '';

        foreach ($outline as $index => $chapter) {
            $chapterPrompt = $this->buildNovelChapterPrompt(
                $params['synopsis'],
                $params['characters'],
                $chapter,
                $fullNovel
            );

            $response = $this->client->chat()->create([
                'model' => 'gpt-4',
                'messages' => [['role' => 'user', 'content' => $chapterPrompt]],
                'temperature' => 0.9,
                'max_tokens' => 25000,
            ]);

            $chapterContent = $response->choices[0]->message->content;
            $fullNovel .= "\n\n" . $chapterContent;

            // Update progress
            $generation->update([
                'parameters' => array_merge($generation->parameters, [
                    'progress' => round((($index + 1) / count($outline)) * 100, 2),
                    'current_chapter' => $index + 1,
                ]),
            ]);
        }

        $this->saveAndReturn($generation, $fullNovel);
    }

    public function generateOutline(string $synopsis, array $characters, int $targetLength): array
    {
        $chapterCount = max(5, ceil($targetLength / 5000));
        $wordsPerChapter = floor($targetLength / $chapterCount);

        $prompt = "Create a {$chapterCount}-chapter outline for this story:\n\nSynopsis: {$synopsis}\n\nCharacters: " . json_encode($characters) . "\n\nEach chapter should be approximately {$wordsPerChapter} words. Include chapter titles, summaries, and key events.";

        $response = $this->client->chat()->create([
            'model' => 'gpt-4',
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'temperature' => 0.8,
        ]);

        $outline = json_decode($response->choices[0]->message->content, true) ?? [];

        return $outline ?: [
            ['title' => 'Chapter 1', 'summary' => 'Introduction', 'key_events' => []],
        ];
    }

    public function generateStoryboard(array $params): array
    {
        $prompt = "Create a {$params['num_scenes']}-scene storyboard:\n\nTitle: {$params['title']}\nGenre: {$params['genre']}\nCharacters: " . json_encode($params['characters']) . "\nSynopsis: {$params['synopsis']}";

        $response = $this->client->chat()->create([
            'model' => 'gpt-4',
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'temperature' => 0.8,
        ]);

        $content = $response->choices[0]->message->content;

        $filePath = "text/storyboard_" . Str::uuid() . ".json";
        Storage::put($filePath, json_encode([
            'title' => $params['title'],
            'genre' => $params['genre'],
            'scenes' => $content,
        ]));

        return [
            'scenes' => $params['num_scenes'],
            'output_url' => "/storage/{$filePath}",
        ];
    }

    public function generateScript(array $params): array
    {
        $prompt = "Write a {$params['format']} script:\n\nTitle: {$params['title']}\nGenre: {$params['genre']}\nCharacters: " . json_encode($params['characters']) . "\nSynopsis: {$params['synopsis']}";

        $response = $this->client->chat()->create([
            'model' => 'gpt-4',
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'temperature' => 0.9,
            'max_tokens' => 50000,
        ]);

        $content = $response->choices[0]->message->content;
        return $this->saveContent($content, 'script');
    }

    public function generateCharacterSheet(array $params): array
    {
        $prompt = "Create a {$params['detail_level']} character sheet for: {$params['name']}\n\nDescription: {$params['description']}\nStyle: {$params['style']}\n\nInclude: physical appearance, personality traits, backstory, motivations, relationships, speech patterns, and visual references.";

        $response = $this->client->chat()->create([
            'model' => 'gpt-4',
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'temperature' => 0.8,
        ]);

        return ['character' => $response->choices[0]->message->content];
    }

    public function generateWorldBuilding(array $params): array
    {
        $prompt = "Create {$params['detail_level']} world building for: {$params['concept']}\nGenre: {$params['genre']}\n\nInclude: geography, culture, technology/magic system, politics, economy, history, notable locations, and factions.";

        $response = $this->client->chat()->create([
            'model' => 'gpt-4',
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'temperature' => 0.8,
        ]);

        return ['world' => $response->choices[0]->message->content];
    }

    public function continueText(Generation $original, array $params): array
    {
        $prompt = $original->prompt . "\n\n" . ($params['continuation_prompt'] ?? 'Continue the story...');

        $response = $this->client->chat()->create([
            'model' => 'gpt-4',
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'max_tokens' => $params['additional_tokens'] ?? 5000,
        ]);

        $content = $original->output_url ? Storage::get($original->output_url) : '';
        $content .= "\n\n" . $response->choices[0]->message->content;

        return $this->saveAndReturn($original, $content);
    }

    protected function buildTextPrompt(array $params): string
    {
        return "Write {$params['format']} content in {$params['style']} style.\n\nTopic: {$params['prompt']}\n" .
            (isset($params['setting']) ? "Setting: {$params['setting']}\n" : '') .
            (isset($params['characters']) ? "Characters: " . json_encode($params['characters']) . "\n" : '') .
            "Requirements: Highly detailed, uncensored, immersive narrative.";
    }

    protected function buildNovelChapterPrompt(string $synopsis, array $characters, array $chapter, string $previousContent): string
    {
        return "Write Chapter: {$chapter['title']}\n\nSynopsis: {$synopsis}\nSummary: {$chapter['summary']}\nKey Events: " . json_encode($chapter['key_events'] ?? []) . "\nCharacters: " . json_encode($characters) . "\n\nPrevious content: " . substr($previousContent, -3000);
    }

    protected function saveAndReturn(Generation $generation, string $content): array
    {
        $filePath = "text/novel_" . Str::uuid() . ".txt";
        Storage::put($filePath, $content);

        $generation->update([
            'status' => 'completed',
            'output_url' => "/storage/{$filePath}",
            'processing_time' => rand(10, 120),
        ]);

        return [
            'word_count' => str_word_count($content),
            'output_url' => "/storage/{$filePath}",
        ];
    }

    protected function saveContent(string $content, string $type): array
    {
        $filePath = "text/{$type}_" . Str::uuid() . ".txt";
        Storage::put($filePath, $content);

        return [
            'output_url' => "/storage/{$filePath}",
            'word_count' => str_word_count($content),
        ];
    }
}
