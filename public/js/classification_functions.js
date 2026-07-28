// === Confidentiality classes (Vertraulichkeitsklassen) ===
// C1/C2: any model allowed. C3: GWDG-provider models only. C4: no model allowed.
const CLASSIFICATION_META = {
    C1: { sub: 'C1 · WHITE', dotClass: 'wht-c', pillClass: 'cls-c1', allowedProviders: null },
    C2: { sub: 'C2 · GREEN', dotClass: 'grn-c', pillClass: 'cls-c2', allowedProviders: null },
    C3: { sub: 'C3 · AMBER', dotClass: 'amb-c', pillClass: 'cls-c3', allowedProviders: ['gwdg'] },
    C4: { sub: 'C4 · RED',   dotClass: 'red-c', pillClass: 'cls-c4', allowedProviders: [] },
};
const CLASSIFICATION_PILL_CLASSES = ['cls-c1', 'cls-c2', 'cls-c3', 'cls-c4'];

let activeClassification = 'C2';

function getClassificationLabel(classId) {
    return translation['Classification_' + classId] || classId;
}
function getClassificationNote(classId) {
    return translation['ClassificationNote_' + classId] || '';
}

// === Selecting a class (input toolbar dropdown) ===
function selectClass(btn) {
    setClass(btn.dataset.classId);
}

function setClass(classId) {
    const meta = CLASSIFICATION_META[classId];
    if (!meta) {
        return;
    }
    activeClassification = classId;

    document.querySelectorAll('.classification-selector').forEach(el => {
        el.classList.toggle('active', el.dataset.classId === classId);
    });
    document.querySelectorAll('.class-selector-dot').forEach(dot => {
        dot.className = 'dot class-selector-dot ' + meta.dotClass;
    });
    document.querySelectorAll('#class-selectors').forEach(el => {
        el.classList.remove(...CLASSIFICATION_PILL_CLASSES);
        el.classList.add(meta.pillClass);
    });
    document.querySelectorAll('.class-selector-label').forEach(label => {
        label.innerHTML = meta.sub;
    });

    document.querySelectorAll('.input-container .input').forEach(inputEl => {
        refreshModelList(inputEl.id);
    });
}

// === Locking (only the class becomes immutable once the conversation exists) ===
// The model stays switchable — it's stored per message, not per conversation, and the
// model catalog changes over time. Locking it too would permanently strand a conversation
// whose model was later removed. The model dropdown remains filtered by the locked class.
function lockClassification() {
    document.querySelectorAll('#class-selectors .burger-btn-arrow').forEach(btn => {
        btn.classList.add('locked');
    });
}
function unlockClassification() {
    document.querySelectorAll('#class-selectors .burger-btn-arrow').forEach(btn => {
        btn.classList.remove('locked');
    });
}

// === Model eligibility hook used by model_list_filtering.js ===
function isProviderAllowedForActiveClassification(provider) {
    const meta = CLASSIFICATION_META[activeClassification];
    if (!meta || meta.allowedProviders === null) {
        return true;
    }
    return meta.allowedProviders.includes(provider);
}

// === Dropdown open/close (mirrors openInputModelSelector in model_functions.js) ===
function openInputClassSelector(sender) {
    if (sender.classList.contains('locked')) {
        return;
    }
    const inputContainer = sender.closest('.input-container');
    const rect = inputContainer.getBoundingClientRect();

    const menu = sender.parentElement.querySelector('#class-selector-burger');
    menu.style.position = 'fixed';
    menu.style.bottom   = `${window.innerHeight - rect.top}px`;
    menu.style.right    = `${window.innerWidth  - rect.right}px`;
    menu.style.left     = '';
    menu.style.top      = '';

    openBurgerMenu('class-selector-burger', sender, false, true, true);
}

// === Info modal with the classification table ===
function openClassInfoModal() {
    const modal = document.getElementById('classification-info-modal');
    modal.querySelector('.content-box').innerHTML = buildClassInfoTableHtml();
    modal.style.display = 'flex';
}

function buildClassInfoTableHtml() {
    const rows = ['C1', 'C2', 'C3', 'C4'].map(classId => {
        const meta = CLASSIFICATION_META[classId];
        return `
            <div class="classification-info-card">
                <div class="classification-info-sub">${meta.sub}</div>
                <div class="classification-info-label">${getClassificationLabel(classId)}</div>
                <div class="classification-info-heading">${translation['ClassificationRestrictionLabel']}</div>
                <div>${translation['ClassificationDesc_' + classId] || ''}</div>
                <div class="classification-info-heading">${translation['ClassificationUsageLabel']}</div>
                <div>${getClassificationNote(classId)}</div>
                <div class="classification-info-heading">${translation['ClassificationExampleLabel']}</div>
                <div>${translation['ClassificationExample_' + classId] || ''}</div>
            </div>`;
    }).join('');

    return `
        <h3>${translation['ClassificationTableTitle']}</h3>
        <div class="classification-info-grid">${rows}</div>
        <div class="row modal-buttons-bar top-gap-3">
            <button class="btn-lg-stroke" onclick="closeModal(this)">${translation['Close']}</button>
        </div>`;
}
