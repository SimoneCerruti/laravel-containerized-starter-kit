<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Safe\mkdir;
use function Safe\symlink;
use function Safe\unlink;

#[Signature('ai:link-hooks {--base-path= : Project to link the hooks in (defaults to this one)}')]
#[Description('Symlink .ai/hooks into the folders the agents read their hooks from')]
final class LinkAiHooksCommand extends Command {
    private const SOURCE = '.ai/hooks';

    /** @var array<string, bool> agent folder => created when missing */
    private const AGENT_FOLDERS = [
        '.claude' => true,
        '.codex' => false,
    ];

    public function handle(): int {
        $base_path = rtrim((string) ($this->option('base-path') ?: base_path()), '/');

        if (! is_dir("{$base_path}/".self::SOURCE)) {
            $this->components->error('There is no '.self::SOURCE.' folder to link.');

            return self::FAILURE;
        }

        $failed = false;

        foreach (self::AGENT_FOLDERS as $folder => $create_when_missing) {
            if (! is_dir("{$base_path}/{$folder}")) {
                if (! $create_when_missing) {
                    continue;
                }

                mkdir("{$base_path}/{$folder}", 0755, true);
            }

            $link = "{$base_path}/{$folder}/hooks";

            if (is_dir($link) && ! is_link($link)) {
                $this->components->error("{$folder}/hooks is a real folder: move its scripts into ".self::SOURCE.' and remove it.');
                $failed = true;

                continue;
            }

            if (is_link($link)) {
                unlink($link);
            }

            symlink('../'.self::SOURCE, $link);
            $this->components->info("{$folder}/hooks -> ".self::SOURCE);
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
