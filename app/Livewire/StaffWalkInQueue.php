<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithToastNotifications;
use App\Models\AdminLog;
use App\Models\QueueEntry;
use App\Rules\PhilippinePhone;
use App\Services\BookingGuardService;
use App\Services\QueueService;
use Livewire\Component;

class StaffWalkInQueue extends Component
{
    use WithToastNotifications;

    public string $customer_name = '';

    public string $customer_phone = '';

    public $party_size = '2';

    public string $priority_type = 'none';

    public function register(): void
    {
        $this->customer_name = trim($this->customer_name);
        $this->customer_phone = trim($this->customer_phone);

        $validated = $this->validate([
            'customer_name' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z\s\-\.]+$/'],
            'customer_phone' => ['nullable', new PhilippinePhone],
            'party_size' => ['required', 'integer', 'min:1', 'max:20'],
            'priority_type' => ['required', 'in:none,pwd,pregnant,senior'],
        ], [
            'customer_name.required' => 'Name is required.',
            'customer_name.regex' => 'Name may only contain letters, spaces, hyphens, and periods.',
            'party_size.required' => 'Party size is required.',
            'party_size.integer' => 'Party size must be a whole number.',
            'party_size.min' => 'Party size must be at least 1.',
            'party_size.max' => 'Party size cannot be more than 20.',
        ]);

        $partySize = (int) $validated['party_size'];

        if (filled($this->customer_phone) && app(BookingGuardService::class)->hasActiveEntry($this->customer_phone)) {
            $this->toastError('This phone already has an active booking or queue entry.');

            return;
        }

        try {
            $entry = app(QueueService::class)->join(
                $this->customer_name,
                $this->customer_phone,
                $partySize,
                $this->priority_type,
                'staff',
                'desktop'
            );
        } catch (\InvalidArgumentException $e) {
            $this->toastError($e->getMessage());

            return;
        }

        AdminLog::record('staff_queue_register', 'queue_entry', $entry->id, 'Walk-in registered at host');

        $this->reset(['customer_name', 'customer_phone', 'party_size', 'priority_type']);
        $this->party_size = '2';
        $this->priority_type = 'none';
        $this->toastSuccess('Added to queue. Ticket #'.$entry->queue_display_number);
    }

    public function render()
    {
        return view('livewire.staff-walk-in-queue', [
            'priorityQueue' => QueueEntry::waiting()->where('priority_score', '>', 0)->sorted()->get(),
            'regularQueue' => QueueEntry::waiting()->where('priority_score', 0)->sorted()->get(),
        ]);
    }
}
