<?php

namespace Acoustica\StatamicSupport\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    protected $signature = 'support:install
                            {--force : Overwrite existing files}
                            {--without-page : Skip creating the support contact page}';

    protected $description = 'Install the Statamic Support package assets and configuration';

    public function handle()
    {
        $this->info('Installing Statamic Support...');

        $this->publishConfig();
        $this->publishViews();
        $this->publishBlueprints();
        $this->publishForms();
        $this->publishFieldsets();

        if (!$this->option('without-page')) {
            $this->publishPage();
        }

        $this->displayEnvInstructions();

        $this->newLine();
        $this->info('Statamic Support installed successfully!');

        return self::SUCCESS;
    }

    protected function publishConfig()
    {
        $this->callSilent('vendor:publish', [
            '--tag' => 'statamic-support-config',
            '--force' => $this->option('force'),
        ]);

        $this->components->task('Publishing configuration');
    }

    protected function publishViews()
    {
        $source = __DIR__ . '/../../resources/views/sections/_support_form_section.antlers.html';
        $destination = resource_path('views/partials/sections/_support_form_section.antlers.html');

        if (File::exists($destination) && !$this->option('force')) {
            $this->components->warn('View already exists, skipping (use --force to overwrite)');
            return;
        }

        File::ensureDirectoryExists(dirname($destination));
        File::copy($source, $destination);

        $this->components->task('Publishing views');
    }

    protected function publishBlueprints()
    {
        $source = __DIR__ . '/../../resources/blueprints/forms/support_contact.yaml';
        $destination = resource_path('blueprints/forms/support_contact.yaml');

        if (File::exists($destination) && !$this->option('force')) {
            $this->components->warn('Form blueprint already exists, skipping (use --force to overwrite)');
            return;
        }

        File::ensureDirectoryExists(dirname($destination));
        File::copy($source, $destination);

        $this->components->task('Publishing form blueprint');
    }

    protected function publishForms()
    {
        $source = __DIR__ . '/../../resources/forms/support_contact.yaml';
        $destination = resource_path('forms/support_contact.yaml');

        if (File::exists($destination) && !$this->option('force')) {
            $this->components->warn('Form config already exists, skipping (use --force to overwrite)');
            return;
        }

        File::ensureDirectoryExists(dirname($destination));
        File::copy($source, $destination);

        $this->components->task('Publishing form config');
    }

    protected function publishFieldsets()
    {
        $source = __DIR__ . '/../../resources/fieldsets/pb_support_form_section.yaml';
        $destination = resource_path('fieldsets/pb_support_form_section.yaml');

        if (File::exists($destination) && !$this->option('force')) {
            $this->components->warn('Fieldset already exists, skipping (use --force to overwrite)');
            return;
        }

        File::ensureDirectoryExists(dirname($destination));
        File::copy($source, $destination);

        $this->components->task('Publishing fieldset');

        $this->newLine();
        $this->components->info('Add this to your page_builder.yaml fieldset to enable the section:');
        $this->newLine();
        $this->line('        support_form_section:');
        $this->line('          display: \'Support Contact Form\'');
        $this->line('          fields: []');
    }

    protected function publishPage()
    {
        $source = __DIR__ . '/../../stubs/support-contact.md.stub';
        $destination = base_path('content/collections/pages/support-contact.md');

        if (File::exists($destination) && !$this->option('force')) {
            $this->components->warn('Support contact page already exists, skipping (use --force to overwrite)');
            return;
        }

        File::ensureDirectoryExists(dirname($destination));

        $content = File::get($source);
        $content = str_replace('{{UUID}}', (string) \Illuminate\Support\Str::uuid(), $content);
        $content = str_replace('{{TIMESTAMP}}', time(), $content);

        File::put($destination, $content);

        $this->components->task('Creating support contact page');
    }

    protected function displayEnvInstructions()
    {
        $this->newLine();
        $this->components->info('Add these variables to your .env file:');
        $this->newLine();

        $envVars = <<<'ENV'
# Set provider to 'kayako' to enable Kayako integration
SUPPORT_PROVIDER=null

# Kayako configuration (only needed if SUPPORT_PROVIDER=kayako)
KAYAKO_URL=https://your-instance.kayako.com
KAYAKO_EMAIL=your-agent@email.com
KAYAKO_PASSWORD=your-password
ENV;

        $this->line($envVars);
    }
}
