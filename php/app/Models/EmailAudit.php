<?php
/**
 * EmailAudit Model
 * Tracks all emails sent by the system
 */

class EmailAudit extends Model
{
    protected static string $table = 'email_audit';
    protected static array $fillable = [
        'email', 'subject', 'body', 'user_id', 'created_at'
    ];
    protected static array $casts = [
        'id' => 'int',
        'user_id' => 'int',
    ];

    /**
     * Log email
     */
    public static function log(string $email, string $subject, string $body, ?int $userId = null): void
    {
        self::create([
            'email' => $email,
            'subject' => $subject,
            'body' => $body,
            'user_id' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get recent emails
     */
    public static function getRecent(int $limit = 50): array
    {
        $sql = 'SELECT * FROM email_audit ORDER BY created_at DESC LIMIT :limit';

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Get emails for user
     */
    public static function getForUser(int $userId): array
    {
        return self::where('user_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->get();
    }
}
