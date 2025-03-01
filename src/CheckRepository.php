<?php

namespace Hexlet\Code;

use Carbon\Carbon;

class CheckRepository
{
    private \PDO $conn;

    public function __construct(\PDO $conn)
    {
        $this->conn = $conn;
    }

    public function addCheck(
        int $urlId,
        int $statusCode,
        ?string $h1,
        ?string $title,
        ?string $description
    ): void {
        $sql = 'INSERT INTO url_checks (url_id, status_code, h1, title, description, created_at)
                VALUES (:url_id, :status_code, :h1, :title, :description, :created_at)';
        $stmt = $this->conn->prepare($sql);
        $date = Carbon::now();
        $stmt->execute([
            'url_id' => $urlId,
            'status_code' => $statusCode,
            'h1' => $h1,
            'title' => $title,
            'description' => $description,
            'created_at' => $date
        ]);
    }

    public function getChecks(int $urlId): array
    {
        $sql = 'SELECT * FROM url_checks WHERE url_id = :url_id ORDER BY created_at DESC';
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(
            ['url_id' => $urlId]
        );
        $result = $stmt->fetchAll();
        return $result;
    }

    public function getLastCheck(int $urlId): ?array
    {
        $sql = 'SELECT status_code, created_at FROM url_checks WHERE url_id = :url_id ORDER BY created_at DESC LIMIT 1';
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(
            ['url_id' => $urlId]
        );
        $result = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        return $result;
    }
}
