<?php

namespace ContentEgg\application\models;

use function ContentEgg\prnx;

defined('\ABSPATH') || exit;

/**
 * PrefillQueueModel handles background product prefill queue entries.
 *
 * @author keywordrush.com
 * @link https://www.keywordrush.com
 */
class PrefillQueueModel extends Model
{
    public function tableName()
    {
        return $this->getDb()->prefix . 'cegg_prefill_queue';
    }

    public function getDump()
    {
        return sprintf(
            "CREATE TABLE %s (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            config_key VARCHAR(64) NOT NULL,
            status ENUM('pending', 'done', 'failed') DEFAULT 'pending',
            log TEXT NULL,
            processing_time FLOAT DEFAULT NULL,
            prompt_tokens INT UNSIGNED DEFAULT NULL,
            completion_tokens INT UNSIGNED DEFAULT NULL,
            ai_cost DECIMAL(10,6) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY uq_post_id (post_id)
        ) %s;",
            $this->tableName(),
            $this->charset_collate
        );
    }

    public static function model($className = __CLASS__)
    {
        return parent::model($className);
    }

    public function attributeLabels()
    {
        return [
            'id'         => 'ID',
            'post_id'    => __('Post ID', 'content-egg'),
            'status'     => __('Status', 'content-egg'),
            'log'        => __('Log', 'content-egg'),
            'created_at' => __('Created At', 'content-egg'),
            'updated_at' => __('Updated At', 'content-egg'),
        ];
    }

    public function save(array $item)
    {
        $db = $this->getDb();
        $item['id'] = isset($item['id']) ? (int)$item['id'] : 0;

        if (!$item['id'])
        {
            unset($item['id']);
            $item['created_at'] = current_time('mysql');
            $db->insert($this->tableName(), $item);
            return (int)$db->insert_id;
        }
        else
        {
            $db->update($this->tableName(), $item, ['id' => $item['id']]);
            return $item['id'];
        }
    }

    public function addToQueue(int $post_id, string $config_key)
    {
        $data = [
            'post_id'    => $post_id,
            'config_key' => $config_key,
            'status'     => 'pending',
            'created_at' => current_time('mysql'),
        ];

        return $this->getDb()->replace($this->tableName(), $data) !== false;
    }

    public function getNextBatch(int $limit = 3): array
    {
        $db = $this->getDb();
        $table = $this->tableName();

        $sql = "SELECT * FROM {$table} WHERE status = %s ORDER BY id ASC LIMIT %d";

        return $db->get_results(
            $db->prepare($sql, 'pending', $limit),
            ARRAY_A
        ) ?: [];
    }

    public function markAsDone(int $post_id, string $log = '', ?float $processing_time = null, ?int $prompt_tokens = null, ?int $completion_tokens = null, ?float $ai_cost = null): bool
    {
        return $this->updateStatus($post_id, 'done', $log, $processing_time, $prompt_tokens, $completion_tokens, $ai_cost);
    }

    public function markAsFailed(
        int $post_id,
        string $log = '',
        ?float $processing_time = null,
        ?int $prompt_tokens = null,
        ?int $completion_tokens = null,
        ?float $ai_cost = null
    ): bool
    {
        return $this->updateStatus($post_id, 'failed', $log, $processing_time, $prompt_tokens, $completion_tokens, $ai_cost);
    }

    protected function updateStatus(
        int $post_id,
        string $status,
        string $log = '',
        ?float $processing_time = null,
        ?int $prompt_tokens = null,
        ?int $completion_tokens = null,
        ?float $ai_cost = null
    ): bool
    {
        $data = [
            'status'     => $status,
            'log'        => $log,
            'updated_at' => current_time('mysql'),
        ];

        if ($processing_time !== null)
        {
            $data['processing_time'] = round($processing_time, 3);
        }

        if ($prompt_tokens !== null)
        {
            $data['prompt_tokens'] = $prompt_tokens;
        }

        if ($completion_tokens !== null)
        {
            $data['completion_tokens'] = $completion_tokens;
        }

        if ($ai_cost !== null)
        {
            $data['ai_cost'] = $ai_cost;
        }

        return $this->getDb()->update(
            $this->tableName(),
            $data,
            ['post_id' => $post_id],
            null,
            ['%d']
        ) !== false;
    }

    public function countPending()
    {
        return $this->countByStatus('pending');
    }

    public function countFailed()
    {
        return $this->countByStatus('failed');
    }

    public function countByStatus(string $status)
    {
        return (int) $this->getDb()->get_var(
            $this->getDb()->prepare(
                "SELECT COUNT(*) FROM {$this->tableName()} WHERE status = %s",
                $status
            )
        );
    }

    public function isInProgress()
    {
        return $this->countPending() > 0;
    }

    public function getLastUpdatedAt(): ?string
    {
        return $this->getDb()->get_var(
            "SELECT MAX(updated_at) FROM {$this->tableName()}"
        );
    }

    public function countAll(): int
    {
        return (int) $this->getDb()->get_var("SELECT COUNT(*) FROM {$this->tableName()}");
    }

    public function clearQueue()
    {
        $this->getDb()->query("DELETE FROM {$this->tableName()}");
    }

    public function clearPending()
    {
        $this->getDb()->delete($this->tableName(), ['status' => 'pending']);
    }

    public function findByPostId(int $post_id): ?array
    {
        $table = $this->tableName();
        return $this->getDb()->get_row(
            $this->getDb()->prepare("SELECT * FROM {$table} WHERE post_id = %d LIMIT 1", $post_id),
            ARRAY_A
        );
    }

    public function restartFailed(): int
    {
        $db = $this->getDb();
        $table = $this->tableName();

        $updated = $db->update(
            $table,
            [
                'status' => 'pending',
                'updated_at' => current_time('mysql'),
            ],
            ['status' => 'failed'],
            ['%s', '%s'],
            ['%s']
        );

        return (int) ($updated !== false ? $updated : 0);
    }
}
