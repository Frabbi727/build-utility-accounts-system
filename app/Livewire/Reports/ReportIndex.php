<?php

namespace App\Livewire\Reports;

use Illuminate\View\View;
use Livewire\Component;

class ReportIndex extends Component
{
    public function render(): View
    {
        return view('livewire.reports.report-index')->layout('components.layouts.app');
    }
}
