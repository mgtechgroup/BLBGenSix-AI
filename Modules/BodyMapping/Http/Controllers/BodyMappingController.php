<?php

namespace Modules\BodyMapping\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\BodyMapping\Services\BodyMappingService;

class BodyMappingController extends Controller
{
    protected BodyMappingService $bodyService;

    public function __construct(BodyMappingService $bodyService)
    {
        $this->bodyService = $bodyService;
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'body_type' => 'string|in:full,face,torso,custom|default:full',
            'gender' => 'string|in:male,female,nonbinary|default:female',
            'age_range' => 'string|in:young_adult,adult,mature|default:adult',
            'body_measurements' => 'nullable|array',
            'pose' => 'string|default:T-pose',
            'detail_level' => 'string|in:low,medium,high,maximum|default:maximum',
            'output_format' => 'string|in:obj,fbx,gltf,stl|default:obj',
            'texture_resolution' => 'string|in:1K,2K,4K,8K|default:4K',
        ]);

        $result = $this->bodyService->generateBodyModel($validated);

        return response()->json([
            'success' => true,
            'model_url' => $result['model_url'],
            'preview_url' => $result['preview_url'],
            'vertex_count' => $result['vertex_count'],
            'face_count' => $result['face_count'],
        ]);
    }

    public function fromImage(Request $request)
    {
        $validated = $request->validate([
            'image' => 'required|file|mimes:png,jpg,jpeg,webp|max:20480',
            'reference_type' => 'string|in:front,side,back,three-quarter|default:front',
            'detail_level' => 'string|default:maximum',
        ]);

        $imagePath = $request->file('image')->store('temp');

        $result = $this->bodyService->createFromImage($imagePath, $validated);

        return response()->json([
            'success' => true,
            'model_url' => $result['model_url'],
            'preview_url' => $result['preview_url'],
        ]);
    }

    public function faceReconstruction(Request $request)
    {
        $validated = $request->validate([
            'image' => 'required|file|mimes:png,jpg,jpeg|max:10240',
            'expression' => 'nullable|string',
            'detail_level' => 'string|default:maximum',
        ]);

        $imagePath = $request->file('image')->store('temp');

        $result = $this->bodyService->reconstructFace($imagePath, $validated);

        return response()->json([
            'success' => true,
            'model_url' => $result['model_url'],
            'preview_url' => $result['preview_url'],
            'landmark_count' => $result['landmark_count'],
        ]);
    }

    public function setPose(Request $request)
    {
        $validated = $request->validate([
            'model_id' => 'required|uuid',
            'pose_data' => 'required|array',
            'pose_preset' => 'nullable|string|in:T-pose,A-pose,standing,sitting,walking,running',
        ]);

        $result = $this->bodyService->setPose($validated['model_id'], $validated['pose_data']);

        return response()->json([
            'success' => true,
            'preview_url' => $result['preview_url'],
        ]);
    }

    public function animate(Request $request)
    {
        $validated = $request->validate([
            'model_id' => 'required|uuid',
            'animation_type' => 'required|string|in:walk,run,dance,idle,custom',
            'duration' => 'integer|min:1|max:300|default:5',
            'loop' => 'boolean|default:true',
        ]);

        $result = $this->bodyService->animate($validated['model_id'], $validated);

        return response()->json([
            'success' => true,
            'animation_url' => $result['animation_url'],
            'preview_url' => $result['preview_url'],
        ]);
    }

    public function applyTexture(Request $request)
    {
        $validated = $request->validate([
            'model_id' => 'required|uuid',
            'texture_type' => 'required|string|in:skin,clothing,custom',
            'texture_image' => 'nullable|file|mimes:png,jpg|max:20480',
            'color_params' => 'nullable|array',
        ]);

        $result = $this->bodyService->applyTexture($validated['model_id'], $validated);

        return response()->json([
            'success' => true,
            'preview_url' => $result['preview_url'],
        ]);
    }

    public function presets()
    {
        return response()->json([
            'body_types' => ['full', 'face', 'torso', 'custom'],
            'pose_presets' => ['T-pose', 'A-pose', 'standing', 'sitting', 'walking', 'running', 'dance'],
            'detail_levels' => ['low', 'medium', 'high', 'maximum'],
            'formats' => ['obj', 'fbx', 'gltf', 'stl'],
        ]);
    }

    public function formats()
    {
        return response()->json([
            'supported_formats' => [
                'obj' => ['texture' => true, 'animation' => false],
                'fbx' => ['texture' => true, 'animation' => true],
                'gltf' => ['texture' => true, 'animation' => true],
                'stl' => ['texture' => false, 'animation' => false],
            ]
        ]);
    }

    public function show($id)
    {
        return response()->json(['id' => $id]);
    }

    public function preview($id)
    {
        return response()->json(['id' => $id]);
    }

    public function destroy($id)
    {
        return response()->json(['success' => true]);
    }
}
