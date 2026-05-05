<?php

namespace Tests\Unit;

use App\Services\FeatureFlagService;
use PHPUnit\Framework\TestCase;

class FeatureFlagServiceTest extends TestCase
{
    protected FeatureFlagService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FeatureFlagService();
    }

    public function test_feature_is_enabled_for_free_plan_when_min_plan_is_free(): void
    {
        $this->assertTrue($this->service->isEnabled('auth_biometric', 'free'));
        $this->assertTrue($this->service->isEnabled('dark_mode', 'free'));
        $this->assertTrue($this->service->isEnabled('webui_search', 'free'));
    }

    public function test_feature_is_disabled_for_free_plan_when_min_plan_is_higher(): void
    {
        $this->assertFalse($this->service->isEnabled('image_generation', 'free'));
        $this->assertFalse($this->service->isEnabled('video_generation', 'free'));
        $this->assertFalse($this->service->isEnabled('body_mapping', 'free'));
    }

    public function test_feature_is_enabled_for_starter_plan_when_min_plan_is_starter(): void
    {
        $this->assertTrue($this->service->isEnabled('image_generation', 'starter'));
        $this->assertTrue($this->service->isEnabled('text_generation', 'starter'));
        $this->assertTrue($this->service->isEnabled('affiliate_program', 'starter'));
    }

    public function test_feature_is_enabled_for_pro_plan_when_min_plan_is_pro(): void
    {
        $this->assertTrue($this->service->isEnabled('body_mapping', 'pro'));
        $this->assertTrue($this->service->isEnabled('crypto_payments', 'pro'));
        $this->assertTrue($this->service->isEnabled('music_analytics', 'pro'));
    }

    public function test_feature_is_enabled_for_enterprise_plan(): void
    {
        $this->assertTrue($this->service->isEnabled('nft_minting', 'enterprise'));
        $this->assertTrue($this->service->isEnabled('dht_search', 'enterprise'));
        $this->assertTrue($this->service->isEnabled('ai_recommendations', 'enterprise'));
    }

    public function test_disabled_feature_returns_false_regardless_of_plan(): void
    {
        $this->service->disableFeature('music_dashboard');
        $this->assertFalse($this->service->isEnabled('music_dashboard', 'enterprise'));

        $this->service->enableFeature('music_dashboard');
        $this->assertTrue($this->service->isEnabled('music_dashboard', 'starter'));
    }

    public function test_unknown_feature_returns_false(): void
    {
        $this->assertFalse($this->service->isEnabled('nonexistent_feature', 'enterprise'));
    }

    public function test_get_all_features_returns_correct_structure(): void
    {
        $features = $this->service->getAllFeatures('pro');

        $this->assertIsArray($features);
        $this->assertArrayHasKey('image_generation', $features);
        $this->assertArrayHasKey('video_generation', $features);

        foreach ($features as $name => $config) {
            $this->assertArrayHasKey('enabled', $config);
            $this->assertArrayHasKey('min_plan', $config);
            $this->assertArrayHasKey('description', $config);
            $this->assertArrayHasKey('category', $config);
        }
    }

    public function test_get_all_features_filters_by_plan(): void
    {
        $freeFeatures = $this->service->getAllFeatures('free');
        $enterpriseFeatures = $this->service->getAllFeatures('enterprise');

        $this->assertFalse($freeFeatures['image_generation']['enabled']);
        $this->assertTrue($enterpriseFeatures['image_generation']['enabled']);
    }

    public function test_get_feature_categories_returns_grouped_features(): void
    {
        $categories = $this->service->getFeatureCategories();

        $this->assertIsArray($categories);
        $this->assertArrayHasKey('core', $categories);
        $this->assertArrayHasKey('generation', $categories);
        $this->assertArrayHasKey('revenue', $categories);
        $this->assertArrayHasKey('ui', $categories);
        $this->assertArrayHasKey('experimental', $categories);
        $this->assertArrayHasKey('music', $categories);
    }

    public function test_set_rollout_percentage(): void
    {
        $this->service->setRollout('image_generation', 50);
        $features = $this->service->getAllFeatures('starter');
        $this->assertEquals(50, $features['image_generation']['min_plan'] === 'starter' ? 50 : 100);
    }

    public function test_enable_feature(): void
    {
        $this->service->disableFeature('music_dashboard');
        $this->assertFalse($this->service->isEnabled('music_dashboard', 'starter'));

        $this->service->enableFeature('music_dashboard');
        $this->assertTrue($this->service->isEnabled('music_dashboard', 'starter'));
    }

    public function test_disable_feature(): void
    {
        $this->service->enableFeature('nft_minting');
        $this->assertTrue($this->service->isEnabled('nft_minting', 'enterprise'));

        $this->service->disableFeature('nft_minting');
        $this->assertFalse($this->service->isEnabled('nft_minting', 'enterprise'));
    }

    public function test_rollout_percentage_affects_enabled_check(): void
    {
        $this->service->setRollout('test_feature_rollout', 0);
        $this->assertFalse($this->service->isEnabled('test_feature_rollout', 'free'));
    }

    public function test_plan_hierarchy_ordering(): void
    {
        $this->assertTrue($this->service->isEnabled('image_generation', 'pro'));
        $this->assertTrue($this->service->isEnabled('image_generation', 'enterprise'));
        $this->assertFalse($this->service->isEnabled('image_generation', 'free'));
    }

    public function test_user_specific_flag_check(): void
    {
        $features = $this->service->getAllFeatures('starter', 'user');
        $this->assertIsArray($features);

        foreach ($features as $feature) {
            $this->assertArrayHasKey('enabled', $feature);
        }
    }

    public function test_music_features_enabled_for_correct_plans(): void
    {
        $this->assertTrue($this->service->isEnabled('music_dashboard', 'starter'));
        $this->assertTrue($this->service->isEnabled('music_connect', 'starter'));
        $this->assertTrue($this->service->isEnabled('music_achievements', 'free'));
        $this->assertFalse($this->service->isEnabled('music_analytics', 'starter'));
        $this->assertTrue($this->service->isEnabled('music_analytics', 'pro'));
    }
}
