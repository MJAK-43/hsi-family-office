<?php

namespace Tests\Feature;

use Tests\TestCase;

class DeploymentSafetyTest extends TestCase
{
    public function test_deployment_scripts_do_not_contain_destructive_or_secret_bearing_commands(): void
    {
        foreach (['deploy/remote_deploy.py', 'deploy/finish_deploy.py'] as $relativePath) {
            $contents = file_get_contents(base_path($relativePath));

            $this->assertStringNotContainsString('migrate:fresh', $contents, $relativePath);
            $this->assertStringNotContainsString('db:seed --force', $contents, $relativePath);
            $this->assertDoesNotMatchRegularExpression('/APP_KEY\s*=\s*["\']base64:/', $contents, $relativePath);
        }
    }
}
