<?php
/**
 * VNN TRAVEL - DATABASE AND SESSION
 *
 * File: config/database.php
 *
 * Ket noi MySQL Aiven.
 * Luu session vao MySQL de hoat dong
 * on dinh hon tren Vercel Serverless.
 */

if (defined('VNN_DATABASE_LOADED')) {
    return;
}

define('VNN_DATABASE_LOADED', true);

/**
 * THONG SO DATABASE
 *
 * Lay tu Environment Variables tren Vercel.
 * Khong viet password truc tiep vao GitHub.
 */
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$dbname = getenv('DB_NAME') ?: 'trekking_db';
$username = getenv('DB_USER') ?: 'root';

$password = getenv('DB_PASSWORD');

if ($password === false) {
    $password = '';
}

/**
 * KET NOI PDO
 */
try {
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

        PDO::ATTR_DEFAULT_FETCH_MODE =>
            PDO::FETCH_ASSOC,

        PDO::ATTR_EMULATE_PREPARES => false,

        PDO::ATTR_TIMEOUT => 10
    ];

    // Giu cau hinh SSL cho MySQL Aiven.
    if (getenv('DB_HOST')) {
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] =
            true;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $host,
        $port,
        $dbname
    );

    $pdo = new PDO(
        $dsn,
        $username,
        $password,
        $options
    );

} catch (PDOException $e) {
    error_log(
        'VNN database connection: ' . $e->getMessage()
    );

    http_response_code(500);

    exit('Không thể kết nối cơ sở dữ liệu.');
}

/**
 * BASE URL
 */
if (!defined('BASE_URL')) {
    $base = getenv('BASE_URL');

    define(
        'BASE_URL',
        ($base !== false && $base !== '')
            ? rtrim($base, '/') . '/'
            : '/'
    );
}

/**
 * TAO BANG LUU SESSION
 *
 * CREATE TABLE IF NOT EXISTS:
 * - Khong xoa bang hien co
 * - Khong xoa du lieu nguoi dung
 * - Chi tao web_sessions neu chua ton tai
 */
if (session_status() === PHP_SESSION_NONE) {

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS web_sessions (
                session_id VARCHAR(128)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                session_data MEDIUMBLOB NOT NULL,

                expires_at BIGINT UNSIGNED NOT NULL,

                PRIMARY KEY (session_id),

                INDEX idx_web_sessions_expiry (expires_at)
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
        ");

    } catch (PDOException $e) {
        error_log(
            'VNN session table: ' . $e->getMessage()
        );

        http_response_code(500);

        exit(
            'Không thể khởi tạo phiên đăng nhập. ' .
            'Vui lòng kiểm tra quyền tạo bảng trên Aiven.'
        );
    }

    /**
     * CUSTOM PHP SESSION HANDLER
     *
     * Session duoc doc/ghi trong database.
     */
    class VnnMysqlSessionHandler
        implements SessionHandlerInterface
    {
        private PDO $pdo;

        public function __construct(PDO $pdo)
        {
            $this->pdo = $pdo;
        }

        public function open(
            string $path,
            string $name
        ): bool {
            return true;
        }

        public function close(): bool
        {
            return true;
        }

        public function read(string $id): string|false
        {
            try {
                $stmt = $this->pdo->prepare("
                    SELECT session_data
                    FROM web_sessions
                    WHERE session_id = ?
                      AND expires_at > ?
                    LIMIT 1
                ");

                $stmt->execute([
                    $id,
                    time()
                ]);

                $data = $stmt->fetchColumn();

                return $data === false
                    ? ''
                    : (string) $data;

            } catch (Throwable $e) {
                error_log(
                    'VNN session read: ' .
                    $e->getMessage()
                );

                return false;
            }
        }

        public function write(
            string $id,
            string $data
        ): bool {
            try {
                $lifetime = (int) ini_get(
                    'session.gc_maxlifetime'
                );

                $lifetime = max(
                    $lifetime,
                    86400
                );

                $expiresAt = time() + $lifetime;

                $stmt = $this->pdo->prepare("
                    INSERT INTO web_sessions (
                        session_id,
                        session_data,
                        expires_at
                    )
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        session_data = VALUES(session_data),
                        expires_at = VALUES(expires_at)
                ");

                return $stmt->execute([
                    $id,
                    $data,
                    $expiresAt
                ]);

            } catch (Throwable $e) {
                error_log(
                    'VNN session write: ' .
                    $e->getMessage()
                );

                return false;
            }
        }

        public function destroy(string $id): bool
        {
            try {
                $stmt = $this->pdo->prepare("
                    DELETE FROM web_sessions
                    WHERE session_id = ?
                ");

                return $stmt->execute([$id]);

            } catch (Throwable $e) {
                error_log(
                    'VNN session destroy: ' .
                    $e->getMessage()
                );

                return false;
            }
        }

        public function gc(int $max_lifetime): int|false
        {
            try {
                $stmt = $this->pdo->prepare("
                    DELETE FROM web_sessions
                    WHERE expires_at <= ?
                ");

                $stmt->execute([time()]);

                return $stmt->rowCount();

            } catch (Throwable $e) {
                error_log(
                    'VNN session cleanup: ' .
                    $e->getMessage()
                );

                return false;
            }
        }
    }

    /**
     * KIEM TRA HTTPS
     */
    $https =
        (!empty($_SERVER['HTTPS']) &&
         $_SERVER['HTTPS'] !== 'off') ||

        (strtolower(
            (string) (
                $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''
            )
        ) === 'https');

    /**
     * THIET LAP COOKIE PHIEN
     */
    ini_set('session.use_only_cookies', '1');

    ini_set('session.use_strict_mode', '1');

    ini_set('session.gc_maxlifetime', '86400');

    ini_set('session.gc_probability', '1');

    ini_set('session.gc_divisor', '100');

    session_name('VNNTRAVELSESSID');

    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'domain' => '',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    /**
     * DANG KY SESSION HANDLER
     */
    $handler = new VnnMysqlSessionHandler($pdo);

    session_set_save_handler(
        $handler,
        true
    );

    /**
     * BAT DAU PHIEN DANG NHAP
     */
    if (!headers_sent()) {
        if (!session_start()) {
            http_response_code(500);

            exit(
                'Không thể khởi tạo phiên đăng nhập.'
            );
        }
    }
}
