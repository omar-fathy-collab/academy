<?php

namespace App\Console\Commands;

use App\Http\Controllers\CertificateController;
use App\Models\Group;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class GenerateGroupCertificates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'certificates:generate-group {group_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate certificates for a group (calls CertificateController::generateGroup)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $groupId = $this->argument('group_id');

        $group = Group::find($groupId);
        if (! $group) {
            $this->error("Group {$groupId} not found.");

            return 1;
        }

        $controller = app(CertificateController::class);

        // Build a request object similar to what the web route would provide
        $request = Request::create('/', 'POST', ['group_id' => $groupId]);

        // Call the controller method
        $response = $controller->generateGroup($request, $group);

        // If redirect response contains session messages, try to display them
        if (is_object($response)) {
            try {
                if (method_exists($response, 'getSession')) {
                    $session = $response->getSession();
                    $msg = $session->get('success') ?? $session->get('error') ?? null;
                    if ($msg) {
                        $this->info($msg);
                    }
                }
            } catch (\Exception $e) {
                // ignore session inspecting errors
            }
        }

        $this->info('Command finished.');

        return 0;
    }
}
