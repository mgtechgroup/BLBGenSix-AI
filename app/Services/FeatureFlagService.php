<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Feature Flag Service - Modular Feature Management
 * Enables/disables features without code changes
 * Supports A/B testing, gradual rollouts, and per-plan gating
 */
class FeatureFlagService
{
    protected array $features = [
        // Core Features
        'auth_biometric'     => ['default' => true,  'min_plan' => 'free'],
        'auth_passkey'       => ['default' => true,  'min_plan' => 'free'],
        
        // Generation Features
        'image_generation'   => ['default' => true,  'min_plan' => 'starter'],
        'video_generation'   => ['default' => true,  'min_plan' => 'starter'],
        'text_generation'    => ['default' => true,  'min_plan' => 'starter'],
        'body_mapping'       => ['default' => true,  'min_plan' => 'pro'],
        'image_upscale'      => ['default' => true,  'min_plan' => 'pro'],
        'batch_generation'   => ['default' => true,  'min_plan' => 'pro'],
        
        // Revenue Features
        'crypto_payments'    => ['default' => true,  'min_plan' => 'pro'],
        'affiliate_program'  => ['default' => true,  'min_plan' => 'starter'],
        'ad_monetization'    => ['default' => true,  'min_plan' => 'pro'],
        'nft_minting'        => ['default' => false, 'min_plan' => 'enterprise'],
        
        // UI Features
        'dark_mode'          => ['default' => true,  'min_plan' => 'free'],
        'admin_dashboard'    => ['default' => true,  'min_plan' => 'free'],
        'analytics_view'     => ['default' => true,  'min_plan' => 'starter'],
        'export_data'        => ['default' => true,  'min_plan' => 'pro'],
        
        // Experimental Features
        'webui_search'       => ['default' => true,  'min_plan' => 'free'],
        'dht_search'         => ['default' => false, 'min_plan' => 'enterprise'],
        'ai_recommendations' => ['default' => false, 'min_plan' => 'enterprise'],
        'live_collab'        => ['default' => false, 'min_plan' => 'enterprise'],
        
        // Music Features
        'music_dashboard'    => ['default' => true,  'min_plan' => 'starter'],
        'music_connect'      => ['default' => true,  'min_plan' => 'starter'],
        'music_analytics'    => ['default' => true,  'min_plan' => 'pro'],
        'music_export'       => ['default' => true,  'min_plan' => 'pro'],
        'music_realtime'     => ['default' => true,  'min_plan' => 'starter'],
        'music_achievements' => ['default' => true,  'min_plan' => 'free'],
    ];

    protected array $planHierarchy = [
        'free'       => 0,
        'starter'    => 10,
        'pro'        => 20,
        'enterprise' => 30,
    ];

    public function isEnabled(string $feature, ?string $userPlan = 'free'): bool
    {
        if (!isset($this->features[$feature])) return false;

        $config = $this->features[$feature];
        
        // Check rollout percentage (A/B testing)
        $rollout = $config['rollout_percentage'] ?? 100;
        if ($rollout < 100 && mt_rand(1, 100) > $rollout) return false;

        // Check plan gate
        $userLevel = $this->planHierarchy[$userPlan] ?? 0;
        $requiredLevel = $this->planHierarchy[$config['min_plan']] ?? 0;
        
        return $userLevel >= $requiredLevel && $config['default'];
    }

    public function getAllFeatures(?string $userPlan = 'free', ?string $role = 'user'): array
    {
        $result = [];
        foreach ($this->features as $name => $config) {
            $result[$name] = [
                'enabled' => $this->isEnabled($name, $userPlan),
                'min_plan' => $config['min_plan'],
                'description' => $config['description'] ?? '',
                'category' => $config['category'] ?? 'general',
            ];
        }
        return $result;
    }

    public function getFeatureCategories(): array
    {
        $categories = [];
        foreach ($this->features as $name => $config) {
            $cat = $config['category'] ?? 'general';
            $categories[$cat][] = $name;
        }
        return $categories;
    }

    public function setRollout(string $feature, int $percentage): void
    {
        if (isset($this->features[$feature])) {
            $this->features[$feature]['rollout_percentage'] = $percentage;
        }
    }

    public function enableFeature(string $feature): void
    {
        if (isset($this->features[$feature])) {
            $this->features[$feature]['default'] = true;
        }
    }

    public function disableFeature(string $feature): void
    {
        if (isset($this->features[$feature])) {
            $this->features[$feature]['default'] = false;
        }
    }
}
