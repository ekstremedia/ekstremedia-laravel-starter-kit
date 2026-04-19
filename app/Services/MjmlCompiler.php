<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

class MjmlCompiler
{
    /**
     * Compile MJML source into HTML.
     *
     * @throws RuntimeException
     */
    public function compile(string $mjml): string
    {
        $tmpBase = tempnam(sys_get_temp_dir(), 'mjml_');
        if ($tmpBase === false) {
            throw new RuntimeException('Failed to create MJML temporary file.');
        }

        // mjml CLI looks at file extension; ensure we feed it a .mjml input.
        $tmpIn = $tmpBase.'.mjml';
        $tmpOut = $tmpBase.'.html';

        try {
            // Rename the 0-byte temp file so the `.mjml` variant is the one
            // we own + clean up, avoiding a zero-byte sibling left behind.
            if (! @rename($tmpBase, $tmpIn)) {
                @unlink($tmpBase);
            }

            if (file_put_contents($tmpIn, $mjml) === false) {
                throw new RuntimeException("Failed to write MJML source to {$tmpIn}.");
            }

            $process = new Process([
                'npx', '--yes', 'mjml', $tmpIn,
                '-o', $tmpOut,
                '--config.validationLevel', 'soft',
            ]);
            $process->setTimeout(30);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException('MJML compilation failed: '.$process->getErrorOutput());
            }

            if (! file_exists($tmpOut)) {
                throw new RuntimeException('MJML compilation produced no output file.');
            }

            $html = file_get_contents($tmpOut);
            if ($html === false) {
                throw new RuntimeException("Failed to read compiled HTML from {$tmpOut}.");
            }

            return $html;
        } finally {
            @unlink($tmpBase);
            @unlink($tmpIn);
            @unlink($tmpOut);
        }
    }
}
