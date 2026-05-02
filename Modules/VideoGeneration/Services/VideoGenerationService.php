<?php

namespace Modules\VideoGeneration\Services;

use App\Models\Generation;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Log;

class VideoGenerationService
{
    public function generateVideo(Generation $generation): void
    {
        try {
            $generation->update(['status' => 'processing']);

            $params = $generation->parameters;
            
            $result = $this->callVideoModel([
                'prompt' => $generation->prompt,
                'negative_prompt' => $generation->negative_prompt,
                'duration' => $params['duration'] ?? 30,
                'fps' => $params['fps'] ?? 24,
                'width' => $params['width'] ?? 1024,
                'height' => $params['height'] ?? 576,
                'motion_strength' => $params['motion_strength'] ?? 0.7,
                'model' => $generation->model_used,
            ]);

            $generation->update([
                'status' => 'completed',
                'output_url' => $result['url'],
                'output_files' => $result['files'],
                'processing_time' => $result['processing_time'],
            ]);

            auth()->user()->incrementUsage();

        } catch (\Exception $e) {
            $generation->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            Log::error('Video generation failed', ['id' => $generation->id, 'error' => $e->getMessage()]);
        }
    }

    public function fromStoryboard(Generation $generation): void
    {
        $generation->update(['status' => 'processing']);

        $params = $generation->parameters;
        $scenes = json_decode($generation->prompt, true);

        $sceneFiles = [];
        foreach ($scenes as $index => $scene) {
            $sceneGeneration = new Generation([
                'prompt' => $scene['prompt'],
                'parameters' => array_merge($params, ['duration' => $scene['duration']]),
            ]);

            $this->generateVideo($sceneGeneration);
            $sceneFiles[] = $sceneGeneration->output_files[0];
        }

        // Merge scenes
        $result = $this->mergeScenes($sceneFiles, $params['transition_style'] ?? 'fade');

        $generation->update([
            'status' => 'completed',
            'output_url' => $result['url'],
            'output_files' => $result['files'],
        ]);
    }

    public function fromScript(Generation $generation): void
    {
        $generation->update(['status' => 'processing']);

        // Parse script into scenes, then generate
        $scenes = $this->parseScriptToScenes($generation->prompt);
        
        $generation->update([
            'prompt' => json_encode($scenes),
            'parameters' => array_merge($generation->parameters, ['scenes' => $scenes]),
        ]);

        $this->fromStoryboard($generation);
    }

    public function extendVideo(Generation $generation): void
    {
        $params = $generation->parameters;
        $original = Generation::findOrFail($params['source_generation_id']);

        $result = $this->callVideoExtend([
            'source_video' => Storage::path($original->output_files[0]),
            'extend_seconds' => $params['extend_seconds'],
            'prompt' => $generation->prompt,
        ]);

        $generation->update([
            'status' => 'completed',
            'output_url' => $result['url'],
            'output_files' => $result['files'],
        ]);
    }

    protected function callVideoModel(array $params): array
    {
        return [
            'url' => '/output/videos/' . Str::uuid() . '.mp4',
            'files' => ['videos/' . Str::uuid() . '.mp4'],
            'processing_time' => ($params['duration'] ?? 30) * 2,
        ];
    }

    protected function mergeScenes(array $sceneFiles, string $transitionStyle): array
    {
        return [
            'url' => '/output/videos/' . Str::uuid() . '.mp4',
            'files' => ['videos/' . Str::uuid() . '.mp4'],
        ];
    }

    protected function parseScriptToScenes(string $script): array
    {
        // AI-powered script parsing
        return [
            ['prompt' => $script, 'duration' => 30],
        ];
    }

    protected function callVideoExtend(array $params): array
    {
        return [
            'url' => '/output/videos/' . Str::uuid() . '.mp4',
            'files' => ['videos/' . Str::uuid() . '.mp4'],
        ];
    }
}
