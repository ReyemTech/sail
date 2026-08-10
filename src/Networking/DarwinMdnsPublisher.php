<?php

namespace Laravel\Sail\Networking;

class DarwinMdnsPublisher
{
    /** @var callable(string): void */
    private $runner;

    public function __construct(?string $directory = null, ?callable $runner = null)
    {
        $this->directory = $directory ?: getenv('HOME').'/Library/LaunchAgents';
        $this->runner = $runner ?? static function (string $command): void {
            shell_exec($command);
        };
    }

    private string $directory;

    public function publish(string $project, string $domain, string $ip): void
    {
        if (! is_dir($this->directory)) {
            mkdir($this->directory, 0755, true);
        }

        $path = $this->path($project);
        file_put_contents($path, $this->plist($project, $domain, $ip));
        ($this->runner)('launchctl bootout gui/'.posix_getuid().' '.escapeshellarg($path).' >/dev/null 2>&1 || true');
        ($this->runner)('launchctl bootstrap gui/'.posix_getuid().' '.escapeshellarg($path));
    }

    public function stop(string $project): void
    {
        $path = $this->path($project);
        if (! is_file($path)) {
            return;
        }

        ($this->runner)('launchctl bootout gui/'.posix_getuid().' '.escapeshellarg($path).' >/dev/null 2>&1 || true');
        unlink($path);
    }

    public function path(string $project): string
    {
        return $this->directory.'/com.reyemtech.sail.mdns.'.$this->slug($project).'.plist';
    }

    private function plist(string $project, string $domain, string $ip): string
    {
        $label = 'com.reyemtech.sail.mdns.'.$this->slug($project);

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">'."\n".
            '<plist version="1.0"><dict><key>Label</key><string>'.$label.'</string><key>ProgramArguments</key><array><string>/usr/bin/dns-sd</string><string>-P</string><string>'.htmlspecialchars($project, ENT_XML1).'</string><string>_https._tcp</string><string>local</string><string>443</string><string>'.htmlspecialchars($domain, ENT_XML1).'</string><string>'.htmlspecialchars($ip, ENT_XML1).'</string></array><key>RunAtLoad</key><true/><key>KeepAlive</key><true/></dict></plist>'."\n";
    }

    private function slug(string $project): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($project)), '-');
    }
}
