<div>
    <h1>Order notes to review ({{ $extractions->total() }})</h1>

    @if ($notice)
        <div class="flash" role="status">{{ $notice }}</div>
    @endif
    @if ($problem)
        <div class="flash problem" role="alert">{{ $problem }}</div>
    @endif

    @forelse ($extractions as $extraction)
        <div class="card" wire:key="extraction-{{ $extraction->id }}">
            <div class="meta">
                Order <strong>{{ $extraction->order->uuid }}</strong>
                · reason <span class="badge">{{ $extraction->review_reason?->value ?? 'none' }}</span>
                @if ($extraction->instructions_detected)
                    <span class="badge warn">note tried to instruct the model</span>
                @endif
                · confidence {{ $extraction->confidence === null ? 'none' : number_format($extraction->confidence, 2) }}
            </div>

            {{-- Untrusted customer text: always escaped, never {!! !!}. --}}
            <div class="note">{{ $extraction->order->note }}</div>

            @if ($extraction->error)
                <div class="meta">Error: {{ $extraction->error }}</div>
            @endif

            <form wire:submit="approve({{ $extraction->id }})">
                <label for="delivery-date-{{ $extraction->id }}">Delivery date</label>
                <input id="delivery-date-{{ $extraction->id }}" type="date" wire:model="fields.{{ $extraction->id }}.delivery_date">
                @error("fields.{$extraction->id}.delivery_date") <div class="error">{{ $message }}</div> @enderror

                <label for="po-number-{{ $extraction->id }}">PO number</label>
                <input id="po-number-{{ $extraction->id }}" type="text" wire:model="fields.{{ $extraction->id }}.po_number">
                @error("fields.{$extraction->id}.po_number") <div class="error">{{ $message }}</div> @enderror

                <label for="invoice-address-{{ $extraction->id }}">Invoice address</label>
                <textarea id="invoice-address-{{ $extraction->id }}" wire:model="fields.{{ $extraction->id }}.invoice_address"></textarea>
                @error("fields.{$extraction->id}.invoice_address") <div class="error">{{ $message }}</div> @enderror

                <div class="actions">
                    <button type="submit" class="approve">Approve</button>
                    <button type="button" class="reject" wire:click="reject({{ $extraction->id }})" wire:confirm="Reject this extraction?">Reject</button>
                </div>
            </form>
        </div>
    @empty
        <div class="card">Nothing to review.</div>
    @endforelse

    {{ $extractions->links() }}
</div>
