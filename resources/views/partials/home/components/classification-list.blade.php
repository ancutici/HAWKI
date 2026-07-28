@php
    $classificationClasses = [
        ['id' => 'C1', 'sub' => 'C1 · WHITE', 'label' => $translation['Classification_C1'], 'dotClass' => 'wht-c'],
        ['id' => 'C2', 'sub' => 'C2 · GREEN', 'label' => $translation['Classification_C2'], 'dotClass' => 'grn-c'],
        ['id' => 'C3', 'sub' => 'C3 · AMBER', 'label' => $translation['Classification_C3'], 'dotClass' => 'amb-c'],
        ['id' => 'C4', 'sub' => 'C4 · RED', 'label' => $translation['Classification_C4'], 'dotClass' => 'red-c'],
    ];
@endphp
<div class="classification-selection-panel">
    <div class="classification-panel-header">
        <span class="classification-panel-title">{{ $translation["Classification"] }}</span>
        <button class="classification-info-btn" onclick="openClassInfoModal(); event.stopPropagation();" title="{{ $translation['ClassificationTableTitle'] }}">?</button>
    </div>
    @foreach($classificationClasses as $class)
        <button class="classification-selector burger-item"
                onclick="selectClass(this); closeBurgerMenus()"
                data-class-id="{{ $class['id'] }}">
            <span class="dot {{ $class['dotClass'] }}"></span>
            <span class="classification-selector-texts">
                <span class="classification-selector-sub">{{ $class['sub'] }}</span>
                <span class="classification-selector-label">{{ $class['label'] }}</span>
            </span>
        </button>
    @endforeach
</div>
