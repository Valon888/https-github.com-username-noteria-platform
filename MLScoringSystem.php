<?php
/**
 * Machine Learning Ready Ad Scoring System
 * Interface for pluggable scoring algorithms including ML models
 */

interface AdScorerInterface {
    /**
     * Calculate ad score for ranking
     *
     * @param array $ad Ad data
     * @param array $userContext User context
     * @param array $historicalData Historical performance data
     * @return float Score between 0-1
     */
    public function calculateScore(array $ad, array $userContext, array $historicalData = []): float;

    /**
     * Get feature vector for ML training
     *
     * @param array $ad Ad data
     * @param array $userContext User context
     * @param array $historicalData Historical performance data
     * @return array Feature vector
     */
    public function getFeatureVector(array $ad, array $userContext, array $historicalData = []): array;

    /**
     * Get feature names for the vector
     *
     * @return array Feature names
     */
    public function getFeatureNames(): array;
}

/**
 * Traditional Relevance-Based Scorer (current algorithm)
 */
class RelevanceBasedScorer implements AdScorerInterface {
    public function calculateScore(array $ad, array $userContext, array $historicalData = []): float {
        // Current relevance scoring logic
        $score = 0.5; // Base score

        // Prefer newer ads
        $createdAt = $ad['created_at'] ?? $ad['start_date'] ?? date('Y-m-d H:i:s');
        $daysOld = (time() - strtotime($createdAt)) / (60 * 60 * 24);
        if ($daysOld < 7) $score += 0.2;

        // Consider click-through rate
        $impressions = $ad['impressions'] ?: 1;
        $ctr = $ad['clicks'] / $impressions;
        if ($ctr > 0.02) $score += 0.2;
        elseif ($ctr < 0.005) $score -= 0.1;

        return min(1.0, max(0.0, $score));
    }

    public function getFeatureVector(array $ad, array $userContext, array $historicalData = []): array {
        $createdAt = $ad['created_at'] ?? $ad['start_date'] ?? date('Y-m-d H:i:s');
        $daysOld = (time() - strtotime($createdAt)) / (60 * 60 * 24);
        $impressions = $ad['impressions'] ?: 1;
        $ctr = $ad['clicks'] / $impressions;

        return [
            // Ad features
            'ad_age_days' => $daysOld,
            'ad_impressions' => $ad['impressions'] ?? 0,
            'ad_clicks' => $ad['clicks'] ?? 0,
            'ad_ctr' => $ctr,
            'ad_type_banner' => ($ad['ad_type'] ?? '') === 'banner' ? 1 : 0,
            'ad_type_card' => ($ad['ad_type'] ?? '') === 'card' ? 1 : 0,
            'ad_type_sidebar' => ($ad['ad_type'] ?? '') === 'sidebar' ? 1 : 0,

            // User context features
            'user_has_id' => isset($userContext['user_id']) ? 1 : 0,
            'device_mobile' => ($userContext['device'] ?? '') === 'mobile' ? 1 : 0,
            'device_desktop' => ($userContext['device'] ?? '') === 'desktop' ? 1 : 0,
            'device_tablet' => ($userContext['device'] ?? '') === 'tablet' ? 1 : 0,
            'location_prishtina' => ($userContext['location'] ?? '') === 'Prishtina' ? 1 : 0,
            'category_inheritance' => ($userContext['category'] ?? '') === 'inheritance' ? 1 : 0,
            'category_contracts' => ($userContext['category'] ?? '') === 'contracts' ? 1 : 0,
            'category_authorization' => ($userContext['category'] ?? '') === 'authorization' ? 1 : 0,
            'hour_of_day' => (int)date('H'),
            'day_of_week' => (int)date('N'),

            // Historical features
            'user_total_views' => $historicalData['user_views'] ?? 0,
            'user_total_clicks' => $historicalData['user_clicks'] ?? 0,
            'user_avg_ctr' => $historicalData['user_avg_ctr'] ?? 0,
        ];
    }

    public function getFeatureNames(): array {
        return [
            'ad_age_days', 'ad_impressions', 'ad_clicks', 'ad_ctr',
            'ad_type_banner', 'ad_type_card', 'ad_type_sidebar',
            'user_has_id', 'device_mobile', 'device_desktop', 'device_tablet',
            'location_prishtina', 'category_inheritance', 'category_contracts', 'category_authorization',
            'hour_of_day', 'day_of_week',
            'user_total_views', 'user_total_clicks', 'user_avg_ctr'
        ];
    }
}

/**
 * Machine Learning Predictive CTR Scorer
 * Placeholder for future ML model integration
 */
class MLPredictiveScorer implements AdScorerInterface {
    private $model; // Future ML model instance

    public function __construct($model = null) {
        $this->model = $model;
    }

    public function calculateScore(array $ad, array $userContext, array $historicalData = []): float {
        if ($this->model) {
            // Use ML model prediction
            $features = $this->getFeatureVector($ad, $userContext, $historicalData);
            return $this->model->predict($features);
        } else {
            // Fallback to relevance scorer
            $fallback = new RelevanceBasedScorer();
            return $fallback->calculateScore($ad, $userContext, $historicalData);
        }
    }

    public function getFeatureVector(array $ad, array $userContext, array $historicalData = []): array {
        // Same as RelevanceBasedScorer for now
        $fallback = new RelevanceBasedScorer();
        return $fallback->getFeatureVector($ad, $userContext, $historicalData);
    }

    public function getFeatureNames(): array {
        $fallback = new RelevanceBasedScorer();
        return $fallback->getFeatureNames();
    }
}

/**
 * ML Training Data Logger
 * Logs all features and outcomes for model training
 */
class MLTrainingDataLogger {
    private PDO $pdo;
    private string $logTable = 'ml_training_data';

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->createTrainingTable();
    }

    /**
     * Log impression data for training
     */
    public function logImpression(array $ad, array $userContext, array $historicalData = []): void {
        $this->logInteraction($ad, $userContext, $historicalData, 'impression', null);
    }

    /**
     * Log click data for training
     */
    public function logClick(array $ad, array $userContext, array $historicalData = []): void {
        $this->logInteraction($ad, $userContext, $historicalData, 'click', 1);
    }

    /**
     * Log non-click impression for training (negative sample)
     */
    public function logNonClick(array $ad, array $userContext, array $historicalData = []): void {
        $this->logInteraction($ad, $userContext, $historicalData, 'impression', 0);
    }

    private function logInteraction(array $ad, array $userContext, array $historicalData, string $interactionType, ?int $clicked): void {
        try {
            $scorer = new RelevanceBasedScorer();
            $features = $scorer->getFeatureVector($ad, $userContext, $historicalData);
            $featureJson = json_encode($features);

            $stmt = $this->pdo->prepare("
                INSERT INTO {$this->logTable} (
                    ad_id, user_id, session_id, interaction_type, clicked,
                    features, user_ip, location, category, device,
                    created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()
                )
            ");

            $stmt->execute([
                $ad['id'],
                $userContext['user_id'] ?? null,
                $userContext['session_id'] ?? null,
                $interactionType,
                $clicked,
                $featureJson,
                $userContext['ip'] ?? null,
                $userContext['location'] ?? null,
                $userContext['category'] ?? null,
                $userContext['device'] ?? null
            ]);

        } catch (Exception $e) {
            error_log("MLTrainingDataLogger: Failed to log interaction: " . $e->getMessage());
        }
    }

    /**
     * Export training data for ML model training
     */
    public function exportTrainingData(int $limit = 10000): array {
        $stmt = $this->pdo->prepare("
            SELECT * FROM {$this->logTable}
            WHERE clicked IS NOT NULL
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create training data table if it doesn't exist
     */
    private function createTrainingTable(): void {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS {$this->logTable} (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    ad_id INT UNSIGNED NOT NULL,
                    user_id INT UNSIGNED,
                    session_id VARCHAR(255),
                    interaction_type ENUM('impression', 'click') NOT NULL,
                    clicked TINYINT, -- 0=no click, 1=clicked, NULL=unknown
                    features JSON,
                    user_ip VARCHAR(45),
                    location VARCHAR(100),
                    category VARCHAR(100),
                    device ENUM('mobile', 'desktop', 'tablet'),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_ad (ad_id),
                    INDEX idx_user (user_id),
                    INDEX idx_session (session_id),
                    INDEX idx_clicked (clicked),
                    INDEX idx_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (Exception $e) {
            error_log("MLTrainingDataLogger: Failed to create training table: " . $e->getMessage());
        }
    }

    /**
     * Clean old training data (keep last N days)
     */
    public function cleanupOldData(int $daysToKeep = 90): void {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM {$this->logTable}
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$daysToKeep]);
        } catch (Exception $e) {
            error_log("MLTrainingDataLogger: Failed to cleanup old data: " . $e->getMessage());
        }
    }
}
?>
