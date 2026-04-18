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
        $tmpIn = tempnam(sys_get_temp_dir(), 'mjml_');
        $tmpOut = $tmpIn.'.html';

        try {
            file_put_contents($tmpIn, $mjml);

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

            return file_get_contents($tmpOut);
        } finally {
            @unlink($tmpIn);
            @unlink($tmpOut);
        }
    }
}
