<button
    type="button"
    class="stage-card-toggle"
    @click="open = !open"
    :aria-expanded="open.toString()"
    aria-controls="{{ $bodyId }}"
>
    <span class="stage-card-heading">
        <span class="stage-card-number">{{ $stageNumber }}</span>
        <span class="stage-card-title">{{ $stageTitle }}</span>
    </span>
    <span class="stage-card-meta">
        <span class="stage-card-status">{{ $stageStatus }}</span>
        <svg class="stage-card-chevron" :class="{ 'rotate-180': open }" aria-hidden="true" viewBox="0 0 20 20" fill="none">
            <path d="m5 7.5 5 5 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </span>
</button>
