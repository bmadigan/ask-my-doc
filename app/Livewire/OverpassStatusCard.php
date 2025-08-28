<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\Overpass\CheckHealthAction;
use Bmadigan\Overpass\Services\PythonAiBridge;
use Livewire\Component;

class OverpassStatusCard extends Component
{
    public $status = null;

    public $testing = false;

    public $lastChecked = null;

    public function mount()
    {
        $this->checkStatus();
    }

    public function checkStatus()
    {
        $this->testing = true;

        try {
            $overpass = app(PythonAiBridge::class);
            $this->status = CheckHealthAction::run($overpass);
            $this->lastChecked = now()->format('H:i:s');

            if ($this->status['success']) {
                $this->dispatch('overpass-status-checked');
            }
        } catch (\Exception $e) {
            $this->status = [
                'success' => false,
                'message' => 'Connection test failed: '.$e->getMessage(),
            ];
        } finally {
            $this->testing = false;
        }
    }

    public function render()
    {
        return view('livewire.overpass-status-card');
    }
}
