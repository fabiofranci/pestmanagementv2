<div class="pm-panel-context" data-panel-context="{{ $appearance['context'] }}">
    <span class="pm-panel-context__pill pm-panel-context__pill--scope">
        <span class="pm-panel-context__eyebrow">
            {{ $appearance['has_tenant'] ? 'Tenant' : 'Modalita' }}
        </span>

        <span class="pm-panel-context__value">
            {{ $appearance['context_label'] }}
        </span>
    </span>

    @if ($appearance['is_superuser'])
        <span class="pm-panel-context__pill pm-panel-context__pill--role">
            Superadmin
        </span>
    @endif
</div>
