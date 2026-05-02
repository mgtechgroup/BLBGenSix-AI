<?php

namespace Modules\VideoGeneration\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Generation;
use Illuminate\Http\Request;
use Modules\VideoGeneration\Services\VideoGenerationService;

class VideoGenerationController extends Controller
{
    protected VideoGenerationService $videoService;

    public function __construct(VideoGenerationService $videoService)
    {
        $this->videoService = $videoService;
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'prompt' => 'required|string|max:3000',
            'negative_prompt' => 'nullable|string|max:3000',
            'duration' => 'integer|min:5|max:300|default:30',
            'fps' => 'integer|min:12|max:60|default:24',
            'width' => 'integer|min:256|max:3840|default:1024',
            'height' => 'integer|min:256|max:2160|default:576',
            'style' => 'string|in:anime,realistic,cinematic|default:realistic',
            'seed' => 'nullable|integer',
            'motion_strength' => 'numeric|min:0|max:1|default:0.7',
            'model' => 'string|default:modelscope',
        ]);

        $generation = Generation::create([
            'user_id' => auth()->id(),
            'type' => Generation::TYPE_VIDEO,
            'model_used' => $validated['model'],
            'prompt' => $validated['prompt'],
            'negative_prompt' => $validated['negative_prompt'] ?? null,
            'parameters' => $validated,
            'status' => 'queued',
        ]);

        $this->videoService->generateVideo($generation);

        return response()->json([
            'success' => true,
            'generation_id' => $generation->id,
            'status' => 'queued',
            'estimated_time' => $validated['duration'] * 2 . 's',
        ]);
    }

    public function fromStoryboard(Request $request)
    {
        $validated = $request->validate([
            'scenes' => 'required|array|min:1|max:50',
            'scenes.*.prompt' => 'required|string|max:2000',
            'scenes.*.duration' => 'integer|min:2|max:60',
            'transition_style' => 'string|in:fade,cut,dissolve,slide|default:fade',
            'total_duration' => 'integer|min:10|max:600',
            'resolution' => 'string|in:720p,1080p,4K|default:1080p',
        ]);

        $generation = Generation::create([
            'user_id' => auth()->id(),
            'type' => Generation::TYPE_VIDEO,
            'model_used' => 'storyboard-pipeline',
            'prompt' => json_encode($validated['scenes']),
            'parameters' => $validated,
            'status' => 'queued',
        ]);

        $this->videoService->fromStoryboard($generation);

        return response()->json([
            'success' => true,
            'generation_id' => $generation->id,
            'scene_count' => count($validated['scenes']),
        ]);
    }

    public function fromScript(Request $request)
    {
        $validated = $request->validate([
            'script' => 'required|string|max:50000',
            'characters' => 'array',
            'style' => 'string|default:cinematic',
            'resolution' => 'string|default:1080p',
        ]);

        $generation = Generation::create([
            'user_id' => auth()->id(),
            'type' => Generation::TYPE_VIDEO,
            'model_used' => 'script-to-video',
            'prompt' => $validated['script'],
            'parameters' => $validated,
            'status' => 'queued',
        ]);

        $this->videoService->fromScript($generation);

        return response()->json([
            'success' => true,
            'generation_id' => $generation->id,
        ]);
    }

    public function edit(Request $request)
    {
        $validated = $request->validate([
            'video_id' => 'required|uuid|exists:generations,id',
            'edit_type' => 'required|string|in:trim,merge,add_audio,add_text,crop',
            'parameters' => 'array',
        ]);

        $original = Generation::findOrFail($validated['video_id']);

        $generation = Generation::create([
            'user_id' => auth()->id(),
            'type' => Generation::TYPE_VIDEO,
            'model_used' => 'video-editor',
            'prompt' => $validated['edit_type'],
            'parameters' => array_merge($validated['parameters'], [
                'source_generation_id' => $original->id,
            ]),
            'status' => 'queued',
        ]);

        return response()->json([
            'success' => true,
            'generation_id' => $generation->id,
        ]);
    }

    public function extend(Request $request)
    {
        $validated = $request->validate([
            'video_id' => 'required|uuid|exists:generations,id',
            'extend_seconds' => 'integer|min:5|max:120',
            'prompt' => 'nullable|string|max:2000',
        ]);

        $original = Generation::findOrFail($validated['video_id']);

        $generation = Generation::create([
            'user_id' => auth()->id(),
            'type' => Generation::TYPE_VIDEO,
            'model_used' => 'video-extend',
            'prompt' => $validated['prompt'] ?? $original->prompt,
            'parameters' => [
                'source_generation_id' => $original->id,
                'extend_seconds' => $validated['extend_seconds'],
            ],
            'status' => 'queued',
        ]);

        $this->videoService->extendVideo($generation);

        return response()->json([
            'success' => true,
            'generation_id' => $generation->id,
        ]);
    }

    public function upscale(Request $request)
    {
        $validated = $request->validate([
            'video_id' => 'required|uuid|exists:generations,id',
            'target_resolution' => 'string|in:1080p,4K,8K|default:4K',
        ]);

        $original = Generation::findOrFail($validated['video_id']);

        $generation = Generation::create([
            'user_id' => auth()->id(),
            'type' => Generation::TYPE_VIDEO,
            'model_used' => 'video-upscaler',
            'prompt' => 'upscale',
            'parameters' => [
                'source_generation_id' => $original->id,
                'target_resolution' => $validated['target_resolution'],
            ],
            'status' => 'queued',
        ]);

        return response()->json([
            'success' => true,
            'generation_id' => $generation->id,
        ]);
    }

    public function formats()
    {
        return response()->json([
            'formats' => [
                'resolutions' => ['720p', '1080p', '4K', '8K'],
                'fps_options' => [12, 24, 30, 60],
                'codecs' => ['h264', 'h265', 'vp9', 'av1'],
                'max_duration' => 300,
            ]
        ]);
    }

    public function history()
    {
        return response()->json(
            auth()->user()
                ->generations()
                ->byType(Generation::TYPE_VIDEO)
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
}
