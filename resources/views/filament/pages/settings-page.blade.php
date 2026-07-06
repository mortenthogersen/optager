<div>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 flex gap-3">
            {{ $this->saveAction }}
        </div>
    </form>
</div>
