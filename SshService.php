<?php

namespace App\Services;

use Closure;
use Exception;
use phpseclib3\Net\SSH2;
use phpseclib3\Net\SFTP;
use phpseclib3\Crypt\PublicKeyLoader;

class SshService {

    /**
     * @var array $config
     */
    private array $config;

    /**
     * @var SSH2|null $ssh2
     */
    private null|SSH2 $ssh2;

    /**
     * @var SFTP|null $sftp
     */
    private null|SFTP $sftp;

    /**
     * @var SSH2|SFTP|null $connection
     */
    private null|SSH2|SFTP $connection;

    /**
     * @param array $config
     * @return void
     * @throws Exception
     */
    public function __construct(array $config) {
        $this->ssh2 = null;
        $this->sftp = null;
        $this->connection = null;
        $host = explode(':', $config['host'])[0];
        $port = explode(':', $config['host'])[1] ?? 22;
        $this->config = $config;
        $this->config['host'] = $host;
        $this->config['port'] = $port;
    }

    /**
     * @param string $id
     * @throws Exception
     */
    public static function into(string $id): SshService {
        $config = config('remote.connections.' . $id);
        if (empty($config)) {
            throw new Exception('Host not found');
        }
        return new self($config);
    }

    /**
     * @param string $file
     * @param string $content
     * @return SshService
     * @throws Exception
     */
    public function putString(string $file, string $content): SshService {
        $this->connection('sftp')
            ->put($file, $content);
        return $this;
    }

    /**
     * @param string $file
     * @param string $content
     * @return SshService
     * @throws Exception
     */
    public function putFile(string $file, string $content): SshService {
        $this->connection('sftp')
            ->put($file, $content, SFTP::SOURCE_LOCAL_FILE);
        return $this;
    }

    /**
     * @param array|string $commands
     * @param Closure|null $callback
     * @return array
     * @throws Exception
     */
    public function run(array|string $commands, Closure $callback = null): array {
        if (!is_array($commands)) {
            $commands = [$commands];
        }
        $ssh = $this->connection();
        $output = [];
        foreach ($commands as $command) {
            if (is_null($callback)) {
                $output[] = $ssh->exec($command);
            } else {
                $ssh->exec($command, function ($line) use ($callback, &$output) {
                    $output[] = $callback($line);
                });
            }
        }
        return $output;
    }

    /**
     * @param string $type
     * @return SSH2|SFTP
     * @throws Exception
     */
    public function connection(string $type = 'ssh'): SSH2|SFTP {
        if ($type === 'ssh') {
            if (!empty($this->ssh2)) {
                return $this->ssh2;
            }
            $this->ssh2 = $this->sshConnection();
            $conn = $this->ssh2;
        } elseif ($type === 'sftp') {
            if (!empty($this->sftp)) {
                return $this->sftp;
            }
            $this->sftp = $this->sftpConnection();
            $conn = $this->sftp;
        } else {
            throw new Exception('Invalid connection type');
        }
        if (!empty($this->config['keytext'])) {
            if (!empty($this->config['keyphrase'])) {
                $key = PublicKeyLoader::load($this->config['keytext'], $this->config['keyphrase']);
            } else {
                $key = PublicKeyLoader::load($this->config['keytext']);
            }
            if (!$conn->login($this->config['username'], $key)) {
                throw new Exception($type . ' Login failed');
            }
        } else {
            if (!$conn->login($this->config['username'], $this->config['password'])) {
                throw new Exception($type . ' Login failed');
            }
        }
        $conn->setTimeout($this->config['timeout']);
        return $this->connection = $conn;
    }

    /**
     * @return SSH2
     */
    private function sshConnection(): SSH2 {
        return new SSH2($this->config['host'], $this->config['port']);
    }

    /**
     * @return SFTP
     */
    private function sftpConnection(): SFTP {
        return new SFTP($this->config['host'], $this->config['port']);
    }

    /**
     * @return false|int
     * @throws Exception
     */
    public function getExitCode(): false|int {
        return $this->connection->getExitStatus();
    }

    /**
     * @return void
     */
    public function __destruct() {
        if (!empty($this->ssh2)) {
            $this->ssh2->disconnect();
        }
        if (!empty($this->sftp)) {
            $this->sftp->disconnect();
        }
    }
}
