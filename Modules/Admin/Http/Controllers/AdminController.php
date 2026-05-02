<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Generation;
use App\Models\Verification;
use App\Models\IncomeStream;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function dashboard()
    {
        return response()->json([
            'total_users' => User::count(),
            'verified_users' => User::where('is_verified_adult', true)->count(),
            'active_subscribers' => User::where('subscription_status', 'active')->count(),
            'total_revenue' => IncomeStream::sum('total_revenue'),
            'total_generations' => Generation::count(),
            'pending_verifications' => Verification::where('status', 'pending')->count(),
        ]);
    }

    public function users(Request $request)
    {
        $query = User::query();

        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('email', 'like', "%{$request->search}%")
                  ->orWhere('username', 'like', "%{$request->search}%");
            });
        }

        if ($request->has('status')) {
            $query->where('subscription_status', $request->status);
        }

        return response()->json(['users' => $query->latest()->paginate(20)]);
    }

    public function banUser(Request $request, $id)
    {
        $validated = $request->validate(['reason' => 'required|string|max:500']);

        User::findOrFail($id)->update([
            'is_banned' => true,
            'ban_reason' => $validated['reason'],
        ]);

        return response()->json(['success' => true]);
    }

    public function unbanUser($id)
    {
        User::findOrFail($id)->update(['is_banned' => false, 'ban_reason' => null]);
        return response()->json(['success' => true]);
    }

    public function verifications()
    {
        return response()->json([
            'verifications' => Verification::where('status', 'pending')->latest()->paginate(20),
        ]);
    }

    public function approveVerification($id)
    {
        $verification = Verification::findOrFail($id);
        $verification->update([
            'status' => 'approved',
            'age_verified' => true,
            'reviewed_at' => now(),
            'reviewed_by' => auth()->id(),
        ]);
        $verification->user->update(['is_verified_adult' => true, 'verified_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function rejectVerification($id, Request $request)
    {
        Verification::findOrFail($id)->update(['status' => 'rejected']);
        return response()->json(['success' => true]);
    }

    public function systemHealth()
    {
        return response()->json([
            'database' => DB::connection()->getPdo() ? 'connected' : 'disconnected',
            'redis' => Redis::ping() ? 'connected' : 'disconnected',
            'queue' => Queue::size() . ' pending jobs',
            'storage' => Storage::disk('s3')->exists('.keep') ? 'connected' : 'disconnected',
        ]);
    }
}
