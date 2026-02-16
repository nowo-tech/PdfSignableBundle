/**
 * @fileoverview AcroForm overrides panel: load/save/clear and list fields.
 * Runs when the DOM has #acroform-editor-root with data-load-url, data-post-url, data-document-key (and optional IDs).
 * Compiled with Vite; no inline JS in Twig.
 */
if (typeof console !== 'undefined') console.debug('[PdfSignable] loaded: acroform-editor.ts');
export type { LabelChoice } from './acroform-editor/index';
import type { AcroFormFieldDescriptor, LabelChoice } from './acroform-editor/index';
import { getConfig, FIELD_NAME_VALUE_OTHER, DEFAULT_STRINGS, escapeAttr } from './acroform-editor/index';

const ICON_EDIT =
  '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>';
const ICON_HIDE =
  '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
const ICON_RESTORE =
  '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
const ICON_MOVE_RESIZE =
  '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 9l-3 3 3 3"/><path d="M9 5l3-3 3 3"/><path d="M15 19l-3 3-3-3"/><path d="M19 9l3 3-3 3"/><path d="M2 12h20"/><path d="M12 2v20"/></svg>';

/**
 * Initializes the AcroForm editor panel: bindings for load/save/clear, fields list,
 * edit modal, move/resize mode, and document key. Subscribes to viewer events for
 * rect changes and add-field placement.
 *
 * @param root - Element with id acroform-editor-root and required child IDs
 */
function initAcroFormEditor(root: HTMLElement): void {
  const win = window as Window & { NowoPdfSignableAcroFormEditorStrings?: Record<string, string> };
  // Strings: prefer #acroform-editor-strings (JSON from Twig), then window override, then defaults
  const stringsEl = root.querySelector('#acroform-editor-strings');
  let strings: Record<string, string> = { ...DEFAULT_STRINGS };
  if (stringsEl?.textContent) {
    try {
      const parsed = JSON.parse(stringsEl.textContent) as Record<string, string>;
      if (parsed && typeof parsed === 'object') strings = { ...strings, ...parsed };
    } catch {
      /* ignore parse error */
    }
  }
  strings = { ...strings, ...(win.NowoPdfSignableAcroFormEditorStrings ?? {}) };
  const str = (key: string, repl?: Record<string, string>): string => {
    let s = strings[key] ?? DEFAULT_STRINGS[key] ?? key;
    if (repl) for (const [k, v] of Object.entries(repl)) s = s.replace(new RegExp('%' + k + '%', 'g'), v);
    return s;
  };
  const config = getConfig(root);
  const docKeyEl = root.querySelector<HTMLInputElement>('#acroform-document-key');
  const jsonEl = root.querySelector<HTMLTextAreaElement>('#acroform-overrides-json');
  const msgEl = root.querySelector<HTMLElement>('#acroform-overrides-message');
  const fieldsSection = root.querySelector<HTMLElement>('#acroform-fields-section');
  const fieldsListEl = root.querySelector<HTMLElement>('#acroform-fields-list');
  const loadBtn = root.querySelector<HTMLButtonElement>('#acroform-load-btn');
  const saveBtn = root.querySelector<HTMLButtonElement>('#acroform-save-btn');
  const clearBtn = root.querySelector<HTMLButtonElement>('#acroform-clear-btn');
  const refreshInputsBtn = root.querySelector<HTMLButtonElement>('#acroform-refresh-inputs-btn');

  if (!docKeyEl || !jsonEl || !msgEl || !fieldsSection || !fieldsListEl || !loadBtn || !saveBtn || !clearBtn) {
    return;
  }

  const jsonSection = root.querySelector<HTMLElement>('#acroform-json-section');
  if (jsonSection && !config.debug) {
    jsonSection.style.display = 'none';
  }
  if (refreshInputsBtn && !config.debug) {
    refreshInputsBtn.style.display = 'none';
  }

  (window as Window & { __pdfSignableAcroFormEditMode?: boolean }).__pdfSignableAcroFormEditMode = true;
  window.dispatchEvent(new CustomEvent('pdf-signable-acroform-edit-mode', { detail: { active: true } }));

  if (!root.querySelector('#acroform-editor-styles')) {
    const style = document.createElement('style');
    style.id = 'acroform-editor-styles';
    style.textContent = '.acroform-btn-icon{display:inline-flex;align-items:center;justify-content:center;min-width:1.75rem}.acroform-btn-icon svg{display:block}.acroform-list-row:hover{background-color:rgba(13,110,253,0.08)}.acroform-list-row.acroform-list-row--focused,.acroform-list-row.acroform-list-row--move-resize{background-color:rgba(220,53,69,0.15);border-left:3px solid #dc3545}';
    root.appendChild(style);
  }
  const useFieldNameSelect = config.fieldNameMode === 'choice' && config.fieldNameChoices.length > 0;
  const NP_AUTOFILL_ATTR = 'data-np-autofill-field-type';

  /** Quita data-np-autofill-field-type de los controles field name para igualarlos al control type (que no lo tiene). */
  function stripNpAutofillFromFieldName(modalEl: HTMLElement): void {
    const select = modalEl.querySelector<HTMLSelectElement>('#acroform-edit-field-name-select');
    const otherInputs = modalEl.querySelectorAll<HTMLInputElement>('#acroform-edit-field-name-other');
    const singleInput = modalEl.querySelector<HTMLInputElement>('#acroform-edit-field-name');
    [select, ...otherInputs, singleInput].filter(Boolean).forEach((el) => {
      if (el?.hasAttribute(NP_AUTOFILL_ATTR)) el.removeAttribute(NP_AUTOFILL_ATTR);
    });
  }

  // Modal markup is rendered by Twig (@NowoPdfSignable/acroform/_edit_modal.html.twig + _edit_modal_body.html.twig).
  // Include editor_root.html.twig so #acroform-edit-modal and .acroform-edit-modal-backdrop exist in the DOM.
  const modal = root.querySelector<HTMLElement>('#acroform-edit-modal');
  const backdrop = root.querySelector<HTMLElement>('.acroform-edit-modal-backdrop');
  if (modal) {
    backdrop?.addEventListener('click', closeEditModal);
    modal.querySelector('#acroform-edit-close')?.addEventListener('click', closeEditModal);
    modal.querySelector('#acroform-edit-cancel')?.addEventListener('click', closeEditModal);
    modal.querySelector('#acroform-edit-save')?.addEventListener('click', saveEditModal);
    modal.querySelector('#acroform-edit-font-auto-size')?.addEventListener('change', updateFontSizeDisabledState);
    // Event delegation: works even when modal content is re-rendered (e.g. Symfony)
    modal.addEventListener('change', (e) => {
      const target = e.target;
      if (target instanceof HTMLSelectElement && target.id === 'acroform-edit-control-type') {
        if (config.debug) console.debug('[AcroForm edit modal] valor seleccionado: control type =', target.value);
        updateModalVisibility(target.value);
      } else if (target instanceof HTMLSelectElement && target.id === 'acroform-edit-field-name-select') {
        if (config.debug) console.debug('[AcroForm edit modal] valor seleccionado: field name =', target.value);
        syncFieldNameOtherVisibility(modal);
      }
    });
    if (useFieldNameSelect) syncFieldNameOtherVisibility(modal);
    // Quitar data-np-autofill-field-type del field name (como el control type no lo tiene)
    const observer = new MutationObserver((mutations) => {
      const added: Array<{ targetId: string | null; targetTag: string }> = [];
      let shouldStrip = false;
      for (const m of mutations) {
        if (m.type === 'attributes' && m.attributeName === NP_AUTOFILL_ATTR && m.target instanceof HTMLElement && m.target.getAttribute(NP_AUTOFILL_ATTR)) {
          shouldStrip = true;
          if (config.debug && m.oldValue == null) {
            added.push({ targetId: m.target.id || null, targetTag: m.target.tagName });
          }
        }
      }
      if (shouldStrip) {
        stripNpAutofillFromFieldName(modal);
        syncFieldNameOtherVisibility(modal);
      }
      if (config.debug && added.length) {
        console.debug('[AcroForm edit modal] extensión añadió', NP_AUTOFILL_ATTR, '→ quitando en', added.length, 'elemento(s)', added);
      }
    });
    observer.observe(modal, { attributes: true, attributeFilter: [NP_AUTOFILL_ATTR], subtree: true, attributeOldValue: true });
  }

  /** Enables or disables font size input based on "auto-adjust font size" checkbox. */
  function updateFontSizeDisabledState(): void {
    const modal = root.querySelector<HTMLElement>('#acroform-edit-modal');
    if (!modal) return;
    const fontSizeEl = modal.querySelector<HTMLInputElement | HTMLSelectElement>('#acroform-edit-font-size');
    const fontAutoSizeEl = modal.querySelector<HTMLInputElement>('#acroform-edit-font-auto-size');
    if (fontSizeEl && fontAutoSizeEl) fontSizeEl.disabled = fontAutoSizeEl.checked;
  }

  /** Shows/hides modal groups (options, default value, checkbox options, font, maxLen) based on control type. */
  function updateModalVisibility(controlType: string): void {
    const modal = root.querySelector<HTMLElement>('#acroform-edit-modal');
    if (!modal) return;
    const optionsGroup = modal.querySelector<HTMLElement>('.acroform-edit-group-options');
    const defaultTextGroup = modal.querySelector<HTMLElement>('.acroform-edit-group-default-text');
    const defaultCheckboxGroup = modal.querySelector<HTMLElement>('.acroform-edit-group-default-checkbox');
    const checkboxOptionsGroup = modal.querySelector<HTMLElement>('.acroform-edit-group-checkbox-options');
    const fontGroup = modal.querySelector<HTMLElement>('.acroform-edit-group-font');
    const maxLenGroup = modal.querySelector<HTMLElement>('.acroform-edit-group-max-len');
    const isChoiceOrSelect = controlType === 'select' || controlType === 'choice';
    if (optionsGroup) optionsGroup.style.display = isChoiceOrSelect ? 'block' : 'none';
    if (defaultTextGroup) defaultTextGroup.style.display = controlType === 'text' || controlType === 'textarea' || isChoiceOrSelect ? 'block' : 'none';
    if (defaultCheckboxGroup) defaultCheckboxGroup.style.display = controlType === 'checkbox' ? 'block' : 'none';
    if (checkboxOptionsGroup) checkboxOptionsGroup.style.display = controlType === 'checkbox' ? 'block' : 'none';
    if (fontGroup) fontGroup.style.display = controlType === 'text' || controlType === 'textarea' ? 'block' : 'none';
    if (maxLenGroup) maxLenGroup.style.display = controlType === 'text' || controlType === 'textarea' ? 'block' : 'none';
    if (controlType === 'text' || controlType === 'textarea') updateFontSizeDisabledState();
  }

  /** Hides the edit modal and its backdrop. */
  function closeEditModal(): void {
    const modal = root.querySelector<HTMLElement>('#acroform-edit-modal');
    const backdrop = root.querySelector<HTMLElement>('.acroform-edit-modal-backdrop');
    if (modal) {
      modal.classList.remove('show');
      modal.style.display = 'none';
      modal.setAttribute('aria-hidden', 'true');
    }
    if (backdrop) {
      backdrop.classList.remove('show');
      backdrop.style.display = 'none';
    }
  }

  /**
   * Shows/hides "field name other" (text input) when field name is a selector, like updateModalVisibility for control type.
   * Only when the selected option is __other__ the text input is shown; otherwise it is always hidden. Driven entirely by TS.
   */
  function syncFieldNameOtherVisibility(modal: HTMLElement): void {
    const fieldNameSelect = modal.querySelector<HTMLSelectElement>('#acroform-edit-field-name-select');
    const hasSelectInModal = !!fieldNameSelect;
    const useSelectMode = useFieldNameSelect || hasSelectInModal;
    if (config.debug) {
      console.debug('[AcroForm field name other] syncFieldNameOtherVisibility llamada', {
        modalId: modal.id,
        useFieldNameSelect,
        hasSelectInModal,
        useSelectMode,
      });
    }
    if (!useSelectMode) {
      if (config.debug) console.debug('[AcroForm field name other] salida: ni config choice ni select en modal');
      return;
    }
    const fieldNameOtherList = modal.querySelectorAll<HTMLInputElement>('#acroform-edit-field-name-other');
    if (config.debug) {
      console.debug('[AcroForm field name other] elementos en modal', {
      tieneSelect: !!fieldNameSelect,
      selectId: fieldNameSelect?.id ?? null,
      numInputsById: fieldNameOtherList.length,
      inputsPorName: modal.querySelectorAll<HTMLInputElement>('input[name="acro_form_field_edit[fieldNameOther]"]').length,
      });
    }
    if (!fieldNameSelect) {
      if (config.debug) console.debug('[AcroForm field name other] salida: no se encontró #acroform-edit-field-name-select');
      return;
    }
    if (fieldNameOtherList.length === 0 && config.debug) {
      const byName = modal.querySelectorAll<HTMLInputElement>('input[name="acro_form_field_edit[fieldNameOther]"]');
      console.debug('[AcroForm field name other] ningún input con id acroform-edit-field-name-other; por name encontrados:', byName.length, byName.length ? Array.from(byName).map((el) => ({ id: el.id, name: el.getAttribute('name') })) : []);
    }
    const selectValue = fieldNameSelect.value;
    const showOther = selectValue === FIELD_NAME_VALUE_OTHER;
    if (config.debug) {
      console.debug('[AcroForm field name other] syncFieldNameOtherVisibility', {
        selectValue,
        FIELD_NAME_VALUE_OTHER,
        showOther,
        fieldNameOtherCount: fieldNameOtherList.length,
      });
    }
    const displayVal = showOther ? 'block' : 'none';
    fieldNameOtherList.forEach((fieldNameOther) => {
      const group = fieldNameOther.closest('.acroform-edit-group');
      const groupHasSelect = group?.querySelector('#acroform-edit-field-name-select') ?? null;
      if (group && !groupHasSelect) {
        (group as HTMLElement).style.display = displayVal;
        if (config.debug) {
          console.debug('[AcroForm edit modal] campo field name other: grupo oculto/visible', {
            display: displayVal,
            grupo: group.tagName,
            grupoClass: group.className,
            selected: selectValue,
          });
        }
      }
      fieldNameOther.style.display = displayVal;
      if (config.debug) {
        console.debug('[AcroForm edit modal] campo field name other: input oculto/visible', {
          display: displayVal,
          id: fieldNameOther.id,
          selected: selectValue,
        });
      }
    });
    if (config.debug && fieldNameOtherList.length) {
      console.debug('[AcroForm edit modal] campo field name other', showOther ? 'visible' : 'oculto', '(selected =', selectValue + ')');
    }
  }

  /** Returns the current field name value from modal (select value or "other" text input or single input). */
  function getFieldNameValueFromModal(modal: HTMLElement): string {
    const fieldNameSelect = modal.querySelector<HTMLSelectElement>('#acroform-edit-field-name-select');
    if (fieldNameSelect) {
      const fieldNameOther = modal.querySelector<HTMLInputElement>('#acroform-edit-field-name-other');
      if (fieldNameSelect.value === FIELD_NAME_VALUE_OTHER && fieldNameOther) return fieldNameOther.value.trim();
      return fieldNameSelect.value.trim();
    }
    const fieldNameEl = modal.querySelector<HTMLInputElement>('#acroform-edit-field-name');
    return fieldNameEl?.value.trim() ?? '';
  }

  /** Applies modal form values to the draft override for the current field and closes the modal. */
  function saveEditModal(): void {
    const modal = root.querySelector<HTMLElement>('#acroform-edit-modal');
    if (!modal) return;
    const fieldIdEl = modal.querySelector<HTMLInputElement>('#acroform-edit-field-id');
    const rectEl = modal.querySelector<HTMLInputElement>('#acroform-edit-rect');
    const controlTypeEl = modal.querySelector<HTMLSelectElement>('#acroform-edit-control-type');
    const optionsEl = modal.querySelector<HTMLTextAreaElement>('#acroform-edit-options');
    const defaultValueEl = modal.querySelector<HTMLInputElement>('#acroform-edit-default-value');
    const defaultCheckedEl = modal.querySelector<HTMLInputElement>('#acroform-edit-default-checked');
    const hasFieldNameControl = !!modal.querySelector('#acroform-edit-field-name-select') || !!modal.querySelector('#acroform-edit-field-name');
    if (!fieldIdEl || !controlTypeEl || !optionsEl || !hasFieldNameControl || !defaultValueEl) return;
    const id = fieldIdEl.value.trim();
    if (!id) return;
    const fieldNameVal = getFieldNameValueFromModal(modal);
    const modalHasFieldNameSelect = !!modal.querySelector('#acroform-edit-field-name-select');
    if (modalHasFieldNameSelect && fieldNameVal === '') {
      showMessage(str('msg_field_name_required'), true);
      return;
    }
    const page = parseInt(fieldIdEl.dataset?.fieldPage ?? '1', 10);
    const controlType = controlTypeEl.value || 'text';
    let rect: number[] | undefined;
    if (rectEl) {
      const rectParts = rectEl.value.split(/[\s,]+/).map((s) => parseFloat(s.trim()));
      const llx = rectParts[0] ?? 0;
      const lly = rectParts[1] ?? 0;
      const w = Math.max(0, rectParts[2] ?? 100);
      const h = Math.max(0, rectParts[3] ?? 20);
      rect = [llx, lly, llx + w, lly + h];
    }
    const optionsStr = (controlType === 'select' || controlType === 'choice') ? optionsEl.value.trim() : '';
    const options: Array<{ value: string; label?: string }> = optionsStr
      ? optionsStr.split(/\n/).map((line) => {
          const pipe = line.indexOf('|');
          if (pipe >= 0) return { value: line.slice(0, pipe).trim(), label: line.slice(pipe + 1).trim() };
          return { value: line.trim(), label: line.trim() };
        })
      : [];
    const defaultValue =
      controlType === 'checkbox' && defaultCheckedEl ? (defaultCheckedEl.checked ? '1' : '0') : defaultValueEl.value;
    const fontSizeEl = modal.querySelector<HTMLInputElement | HTMLSelectElement>('#acroform-edit-font-size');
    const fontFamilyEl = modal.querySelector<HTMLSelectElement>('#acroform-edit-font-family');
    const fontAutoSizeEl = modal.querySelector<HTMLInputElement>('#acroform-edit-font-auto-size');
    const fontSizeVal = fontSizeEl?.value.trim() ? parseInt(fontSizeEl.value, 10) : NaN;
    const fontFamilyVal = fontFamilyEl?.value?.trim() ?? '';
    const fontAutoSizeVal = fontAutoSizeEl?.checked ?? false;
    if (!draftOverrides[id]) draftOverrides[id] = {};
    if (rect) draftOverrides[id].rect = rect;
    draftOverrides[id].page = page;
    draftOverrides[id].controlType = controlType;
    draftOverrides[id].options = options.length ? options : undefined;
    if (fieldNameVal !== '') draftOverrides[id].fieldName = fieldNameVal;
    const maxLenEl = modal.querySelector<HTMLInputElement>('#acroform-edit-max-len');
    if (maxLenEl && maxLenEl.value.trim() !== '') {
      const ml = parseInt(maxLenEl.value, 10);
      if (!Number.isNaN(ml) && ml >= 0) draftOverrides[id].maxLen = ml;
    }
    const hiddenEl = modal.querySelector<HTMLInputElement>('#acroform-edit-hidden');
    if (hiddenEl) draftOverrides[id].hidden = hiddenEl.checked;
    const createIfMissingEl = modal.querySelector<HTMLInputElement>('#acroform-edit-create-if-missing');
    if (createIfMissingEl) draftOverrides[id].createIfMissing = createIfMissingEl.checked;
    draftOverrides[id].defaultValue = defaultValue;
    if (controlType === 'text' || controlType === 'textarea') {
      const defaultFontSize = 11;
      const defaultFontFamily = 'sans-serif';
      draftOverrides[id].fontSize = !Number.isNaN(fontSizeVal) && fontSizeVal > 0 ? fontSizeVal : defaultFontSize;
      draftOverrides[id].fontFamily = fontFamilyVal || defaultFontFamily;
      draftOverrides[id].fontAutoSize = fontAutoSizeVal;
    }
    if (controlType === 'checkbox') {
      const valueOnEl = modal.querySelector<HTMLInputElement>('#acroform-edit-checkbox-value-on');
      const valueOffEl = modal.querySelector<HTMLInputElement>('#acroform-edit-checkbox-value-off');
      const iconEl = modal.querySelector<HTMLSelectElement>('#acroform-edit-checkbox-icon');
      const valueOn = valueOnEl?.value.trim() ?? '';
      const valueOff = valueOffEl?.value.trim() ?? '';
      const icon = (iconEl?.value?.trim() || 'check') as 'check' | 'cross' | 'dot';
      draftOverrides[id].checkboxValueOn = valueOn !== '' ? valueOn : '1';
      draftOverrides[id].checkboxValueOff = valueOff !== '' ? valueOff : '0';
      draftOverrides[id].checkboxIcon = icon;
    }
    syncDraftToTextarea();
    renderFieldsList(lastLoadedFields.length ? lastLoadedFields : getFieldsFromViewer(), draftOverrides);
    notifyViewerOverrides();
    closeEditModal();
  }

  const _msgEl = msgEl;
  const _jsonEl = jsonEl;
  const _docKeyEl = docKeyEl;
  const _fieldsSection = fieldsSection;
  const _fieldsListEl = fieldsListEl;
  const _loadBtn = loadBtn;

  if (config.documentKey) {
    _docKeyEl.value = config.documentKey;
  }

  let draftOverrides: Record<string, Record<string, unknown>> = {};
  let lastLoadedFields: AcroFormFieldDescriptor[] = [];
  let lastAppliedPdf: ArrayBuffer | null = null;
  /** Counter for default name of new fields: "New field 1", "New field 2", … (editable in modal). */
  let newFieldCounter = 0;

  /** Patch payload for apply: full field override so backend/Python can edit the PDF completely. */
  type AcroFormPatchPayload = {
    fieldId: string;
    rect?: number[];
    defaultValue?: string;
    hidden?: boolean;
    controlType?: string;
    fieldType?: string;
    options?: Array<{ value: string; label?: string }>;
    page?: number;
    fieldName?: string;
    maxLen?: number;
    fontSize?: number;
    fontFamily?: string;
    createIfMissing?: boolean;
  };

  /** Builds patch list from current draft overrides for apply/save. Sends all override data per field. */
  function buildPatchesFromOverrides(): AcroFormPatchPayload[] {
    const patches: AcroFormPatchPayload[] = [];
    Object.keys(draftOverrides).forEach((fieldId) => {
      const o = draftOverrides[fieldId];
      if (!o || typeof o !== 'object') return;
      const p: AcroFormPatchPayload = { fieldId };
      if (Array.isArray(o.rect) && o.rect.length >= 4) p.rect = o.rect as number[];
      if (o.defaultValue !== undefined) p.defaultValue = String(o.defaultValue);
      if (o.hidden === true || o.hidden === 'true') p.hidden = true;
      if (o.fieldName !== undefined && String(o.fieldName).trim() !== '') p.fieldName = String(o.fieldName).trim();
      if (o.controlType !== undefined && String(o.controlType)) p.controlType = String(o.controlType);
      if (o.fieldType !== undefined && String(o.fieldType)) p.fieldType = String(o.fieldType);
      if (Array.isArray(o.options) && o.options.length) {
        p.options = o.options.map((opt: unknown) => {
          if (typeof opt === 'string') return { value: opt, label: opt };
          if (opt && typeof opt === 'object' && 'value' in (opt as object))
            return { value: String((opt as { value: string }).value), label: (opt as { label?: string }).label };
          return { value: '', label: '' };
        }).filter((x: { value: string }) => x.value !== '');
      }
      if (o.page !== undefined && Number.isFinite(Number(o.page))) p.page = Number(o.page);
      if (o.createIfMissing === true || o.createIfMissing === 'true') p.createIfMissing = true;
      if (fieldId.startsWith('new-') && p.rect && p.page) p.createIfMissing = true;
      if (o.maxLen !== undefined && Number.isFinite(Number(o.maxLen))) p.maxLen = Number(o.maxLen);
      if (o.fontSize !== undefined && Number.isFinite(Number(o.fontSize))) p.fontSize = Number(o.fontSize);
      if (o.fontFamily !== undefined && String(o.fontFamily).trim() !== '') p.fontFamily = String(o.fontFamily).trim();
      patches.push(p);
    });
    return patches;
  }

  /** Displays a message in the panel (normal or error style). */
  function showMessage(text: string, isError: boolean): void {
    _msgEl.textContent = text;
    _msgEl.className = 'small mb-2 ' + (isError ? 'text-danger' : 'text-success');
  }

  /** Serializes draft overrides to JSON and writes to the textarea. */
  function syncDraftToTextarea(): void {
    _jsonEl.value = JSON.stringify(draftOverrides, null, 2);
  }

  /** Parses textarea JSON into draft overrides. Returns false if JSON is invalid. */
  function syncTextareaToDraft(): boolean {
    try {
      const raw = (_jsonEl.value || '').trim();
      const parsed = raw ? JSON.parse(raw) : {};
      draftOverrides = typeof parsed === 'object' && parsed !== null ? parsed : {};
    } catch {
      return false;
    }
    return true;
  }

  /** Returns current AcroForm field list from the viewer (via custom event or empty). */
  function getFieldsFromViewer(): AcroFormFieldDescriptor[] {
    const w = window as Window & { __pdfSignableAcroFormFields?: AcroFormFieldDescriptor[] };
    if (w.__pdfSignableAcroFormFields && Array.isArray(w.__pdfSignableAcroFormFields) && w.__pdfSignableAcroFormFields.length) {
      return w.__pdfSignableAcroFormFields.map((f) => ({
        id: f.id,
        rect: f.rect,
        width: f.width,
        height: f.height,
        fieldType: f.fieldType ?? '',
        value: f.value,
        page: f.page,
        subtype: f.subtype,
        flags: f.flags,
        fieldName: f.fieldName,
        maxLen: f.maxLen,
        fontSize: f.fontSize,
      }));
    }
    return [];
  }

  /** Collects current field values from the viewer form inputs (by name). */
  function getCurrentValuesFromInputs(): Record<string, string> {
    const out: Record<string, string> = {};
    document.querySelectorAll<HTMLInputElement | HTMLTextAreaElement>('.acroform-field-input').forEach((input) => {
      const id = input.dataset?.fieldId;
      if (id != null) out[id] = input.value ?? '';
    });
    return out;
  }

  /** Builds full overrides payload (fields + patches + current values) for save/apply. */
  function buildFullOverrides(
    fields: AcroFormFieldDescriptor[],
    overrides: Record<string, Record<string, unknown>>,
    liveValues: Record<string, string>,
  ): Record<string, Record<string, unknown>> {
    const byId: Record<string, AcroFormFieldDescriptor> = {};
    fields.forEach((f) => {
      byId[String(f.id)] = f;
    });
    const result: Record<string, Record<string, unknown>> = {};
    const allIds = new Set<string>([...Object.keys(overrides || {}), ...fields.map((f) => String(f.id ?? ''))]);
    allIds.forEach((id) => {
      const full = byId[id] ?? {};
      const base: Record<string, unknown> = {
        id: full.id,
        rect: full.rect,
        width: full.width,
        height: full.height,
        fieldType: full.fieldType,
        value: full.value,
        page: full.page,
        subtype: full.subtype,
        flags: full.flags,
        fieldName: full.fieldName,
        maxLen: full.maxLen,
        fontSize: full.fontSize,
      };
      const current = overrides[id] ?? {};
      let defaultValue: string | undefined = liveValues[id];
      if (defaultValue === undefined) defaultValue = current.defaultValue as string | undefined;
      if (defaultValue === undefined) defaultValue = full.value as string | undefined;
      result[id] = { ...base, ...current, defaultValue };
    });
    return result;
  }

  /** Notify the PDF viewer to apply current overrides (hidden, rect, controlType, etc.); dispatches pdf-signable-acroform-overrides-updated. */
  function notifyViewerOverrides(): void {
    const w = window as Window & { __pdfSignableAcroFormOverrides?: Record<string, Record<string, unknown>> };
    w.__pdfSignableAcroFormOverrides = draftOverrides;
    window.dispatchEvent(new CustomEvent('pdf-signable-acroform-overrides-updated', { detail: { overrides: draftOverrides } }));
  }

  /** Updates draft overrides with current values from the viewer inputs. */
  function mergeInputsIntoDraft(): void {
    const live = getCurrentValuesFromInputs();
    for (const id of Object.keys(live)) {
      if (!draftOverrides[id]) draftOverrides[id] = {};
      draftOverrides[id].defaultValue = live[id];
    }
  }

  /** Sets or clears the hidden flag for a field in the draft overrides. */
  function setOverrideHidden(fieldId: string, hidden: boolean): void {
    if (!draftOverrides[fieldId]) draftOverrides[fieldId] = {};
    draftOverrides[fieldId].hidden = hidden;
    syncDraftToTextarea();
    renderFieldsList(lastLoadedFields.length ? lastLoadedFields : getFieldsFromViewer(), draftOverrides);
    notifyViewerOverrides();
  }

  /** Opens the edit modal and fills it with the given field descriptor and override config. */
  function openEditModal(field: AcroFormFieldDescriptor, cfg: Record<string, unknown>): void {
    const id = String(field.id ?? '');
    const rect = (cfg.rect ?? field.rect) as number[] | undefined;
    const r = rect && rect.length >= 4 ? rect : (field.rect && field.rect.length >= 4 ? field.rect : [0, 0, 100, 20]);
    const llx = r[0];
    const lly = r[1];
    const urx = r[2];
    const ury = r[3];
    const width = (cfg.width ?? field.width ?? Math.max(0, urx - llx)) as number;
    const height = (cfg.height ?? field.height ?? Math.max(0, ury - lly)) as number;
    const controlType = (cfg.controlType ?? (field.fieldType === 'Ch' ? 'choice' : field.fieldType === 'Btn' ? 'checkbox' : 'text')) as string;
    const options = (cfg.options ?? []) as Array<{ value?: string; label?: string }>;
    const optionsStr = options.map((o) => (o.value ?? '') + (o.label ? '|' + o.label : '')).join('\n');
    const defVal = (cfg.defaultValue ?? field.value ?? '') as string;
    const modal = root.querySelector<HTMLElement>('#acroform-edit-modal');
    if (!modal) return;
    const fieldIdEl = modal.querySelector<HTMLInputElement>('#acroform-edit-field-id');
    const rectEl = modal.querySelector<HTMLInputElement>('#acroform-edit-rect');
    const controlTypeEl = modal.querySelector<HTMLSelectElement>('#acroform-edit-control-type');
    const optionsEl = modal.querySelector<HTMLTextAreaElement>('#acroform-edit-options');
    const defaultValueEl = modal.querySelector<HTMLInputElement>('#acroform-edit-default-value');
    const defaultCheckedEl = modal.querySelector<HTMLInputElement>('#acroform-edit-default-checked');
    if (!fieldIdEl || !controlTypeEl || !optionsEl || !defaultValueEl) return;
    fieldIdEl.value = id;
    fieldIdEl.dataset.fieldPage = String(field.page ?? 1);
    const fieldNameVal = (cfg.fieldName ?? field.fieldName ?? id ?? '') as string;
    const fieldNameSelect = modal.querySelector<HTMLSelectElement>('#acroform-edit-field-name-select');
    const fieldNameEl = modal.querySelector<HTMLInputElement>('#acroform-edit-field-name');
    if (fieldNameSelect) {
      const fieldNameOther = modal.querySelector<HTMLInputElement>('#acroform-edit-field-name-other');
      const optionValues = Array.from(fieldNameSelect.options).map((o) => o.value);
      const existsInOptions = optionValues.includes(fieldNameVal);
      if (existsInOptions) {
        fieldNameSelect.value = fieldNameVal;
        if (fieldNameOther) fieldNameOther.value = '';
      } else {
        fieldNameSelect.value = FIELD_NAME_VALUE_OTHER;
        if (fieldNameOther) fieldNameOther.value = fieldNameVal;
      }
    } else if (fieldNameEl) {
      fieldNameEl.value = fieldNameVal;
    }
    syncFieldNameOtherVisibility(modal);
    controlTypeEl.value = controlType;
    if (rectEl) rectEl.value = [llx.toFixed(1), lly.toFixed(1), width.toFixed(1), height.toFixed(1)].join(', ');
    optionsEl.value = optionsStr;
    defaultValueEl.value = controlType === 'checkbox' ? '' : defVal;
    if (defaultCheckedEl) defaultCheckedEl.checked = /^(1|true|yes|on)$/i.test(defVal.trim());
    const valueOnEl = modal.querySelector<HTMLInputElement>('#acroform-edit-checkbox-value-on');
    const valueOffEl = modal.querySelector<HTMLInputElement>('#acroform-edit-checkbox-value-off');
    const checkboxIconEl = modal.querySelector<HTMLSelectElement>('#acroform-edit-checkbox-icon');
    if (valueOnEl) valueOnEl.value = (cfg.checkboxValueOn as string) ?? '1';
    if (valueOffEl) valueOffEl.value = (cfg.checkboxValueOff as string) ?? '0';
    if (checkboxIconEl) {
      const icon = (cfg.checkboxIcon as string) || 'check';
      checkboxIconEl.value = icon === 'cross' || icon === 'dot' ? icon : 'check';
    }
    const fontSizeEl = modal.querySelector<HTMLInputElement | HTMLSelectElement>('#acroform-edit-font-size');
    const fontFamilyEl = modal.querySelector<HTMLSelectElement>('#acroform-edit-font-family');
    const fontAutoSizeEl = modal.querySelector<HTMLInputElement>('#acroform-edit-font-auto-size');
    const defaultFontSize = 11;
    const defaultFontFamily = 'sans-serif';
    if (fontSizeEl) {
      const fs = typeof cfg.fontSize === 'number' && cfg.fontSize > 0 ? cfg.fontSize : defaultFontSize;
      fontSizeEl.value = String(fs);
    }
    if (fontFamilyEl) fontFamilyEl.value = (typeof cfg.fontFamily === 'string' && cfg.fontFamily) ? cfg.fontFamily : defaultFontFamily;
    if (fontAutoSizeEl) fontAutoSizeEl.checked = !!(cfg.fontAutoSize === true);
    const maxLenEl = modal.querySelector<HTMLInputElement>('#acroform-edit-max-len');
    if (maxLenEl) {
      const ml = typeof cfg.maxLen === 'number' && cfg.maxLen >= 0 ? cfg.maxLen : (field.maxLen ?? undefined);
      maxLenEl.value = ml !== undefined && ml !== null ? String(ml) : '';
    }
    const hiddenEl = modal.querySelector<HTMLInputElement>('#acroform-edit-hidden');
    if (hiddenEl) hiddenEl.checked = !!(cfg.hidden === true);
    const createIfMissingEl = modal.querySelector<HTMLInputElement>('#acroform-edit-create-if-missing');
    if (createIfMissingEl) createIfMissingEl.checked = !!(cfg.createIfMissing === true);
    updateModalVisibility(controlType);
    updateFontSizeDisabledState();
    stripNpAutofillFromFieldName(modal);
    modal.classList.add('show');
    modal.style.display = 'block';
    modal.setAttribute('aria-hidden', 'false');
    const backdrop = root.querySelector<HTMLElement>('.acroform-edit-modal-backdrop');
    if (backdrop) {
      backdrop.classList.add('show');
      backdrop.style.display = 'block';
    }
    requestAnimationFrame(() => stripNpAutofillFromFieldName(modal));
  }

  /**
   * Renders the list of AcroForm fields (field name, type, page, actions) and wires row click, edit, move/resize, hide/restore.
   *
   * @param fields - Field descriptors from the viewer or load response
   * @param overrides - Optional override map (field id => config); uses draftOverrides when omitted
   */
  function renderFieldsList(fields: AcroFormFieldDescriptor[], overrides?: Record<string, Record<string, unknown>>): void {
    if (!fields.length) {
      _fieldsSection.style.display = 'none';
      return;
    }
    _fieldsSection.style.display = 'block';
    const sorted = [...fields].sort((a, b) => {
      const pageA = Number(a.page ?? 1);
      const pageB = Number(b.page ?? 1);
      if (pageA !== pageB) return pageA - pageB;
      const rectA = a.rect;
      const rectB = b.rect;
      if (rectA && rectB && rectA.length >= 4 && rectB.length >= 4) {
        const topA = Math.max(rectA[1], rectA[3]);
        const topB = Math.max(rectB[1], rectB[3]);
        return topB - topA;
      }
      return 0;
    });
    lastLoadedFields = sorted;
    const opts = overrides ?? draftOverrides;
    const liveValues = getCurrentValuesFromInputs();
    const rows = sorted.map((f) => {
      const id = String(f.id ?? '');
      const cfg = opts[id] ?? {};
      const isHidden = !!cfg.hidden;
      const currentVal: string = liveValues[id] ?? f.value ?? '';
      const fieldNameDisplay = String(cfg.fieldName ?? f.fieldName ?? (f as { label?: string }).label ?? '-');
      const parts: string[] = [str('list_field_name') + fieldNameDisplay, str('list_type') + (f.fieldType ?? '-'), str('list_page') + (f.page ?? '')];
      if (currentVal !== undefined && currentVal !== '') parts.push(str('list_current_value') + currentVal);
      if (cfg.defaultValue) parts.push('defaultValue: ' + String(cfg.defaultValue));
      const page = f.page ?? 1;
      const hideBtn = isHidden
        ? `<button type="button" class="btn btn-sm btn-outline-success acroform-btn-icon py-0 px-1 acroform-btn-restore" data-field-id="${escapeAttr(id)}" title="${escapeAttr(str('btn_restore_title'))}">${ICON_RESTORE}</button>`
        : `<button type="button" class="btn btn-sm btn-outline-danger acroform-btn-icon py-0 px-1 acroform-btn-hide" data-field-id="${escapeAttr(id)}" title="${escapeAttr(str('btn_hide_title'))}">${ICON_HIDE}</button>`;
      const editBtn = `<button type="button" class="btn btn-sm btn-outline-primary acroform-btn-icon py-0 px-1 acroform-btn-edit" data-field-id="${escapeAttr(id)}" title="${escapeAttr(str('btn_edit_title'))}">${ICON_EDIT}</button>`;
      const moveResizeBtn = `<button type="button" class="btn btn-sm btn-outline-secondary acroform-btn-icon py-0 px-1 acroform-btn-move-resize" data-field-id="${escapeAttr(id)}" data-page="${page}" title="${escapeAttr(str('btn_move_resize_title'))}">${ICON_MOVE_RESIZE}</button>`;
      const rowClass = 'acroform-list-row border-bottom pb-1 mb-1 d-flex justify-content-between align-items-center gap-2 ' + (isHidden ? 'text-muted bg-light' : 'cursor-pointer');
      const textPart = (parts.join(' · ') || '-') + (isHidden ? str('list_hidden_suffix') : '');
      return `<div class="${rowClass}" data-field-id="${escapeAttr(id)}" data-page="${page}" role="button" tabindex="0" title="${escapeAttr(str('row_click_highlight'))}"><span class="acroform-row-text flex-grow-1 text-truncate">${escapeAttr(textPart)}</span><span class="acroform-row-actions d-flex gap-1 flex-shrink-0">${editBtn}${moveResizeBtn}${hideBtn}</span></div>`;
    });
    _fieldsListEl.innerHTML = rows.join('') || '<span class="text-muted">' + escapeAttr(str('list_no_fields')) + '</span>';

    _fieldsListEl.querySelectorAll('.acroform-btn-hide').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        setOverrideHidden(String((e.currentTarget as HTMLElement).dataset.fieldId || ''), true);
      });
    });
    _fieldsListEl.querySelectorAll('.acroform-btn-restore').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        setOverrideHidden(String((e.currentTarget as HTMLElement).dataset.fieldId || ''), false);
      });
    });
    _fieldsListEl.querySelectorAll('.acroform-btn-edit').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        clearMoveResizeState();
        highlightListRowForMoveResize(undefined);
        const fieldId = (e.currentTarget as HTMLElement).dataset.fieldId || '';
        const field = fields.find((f) => String(f.id) === fieldId);
        if (field) openEditModal(field, opts[fieldId] ?? {});
      });
    });
    _fieldsListEl.querySelectorAll('.acroform-btn-move-resize').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        closeEditModal();
        const el = e.currentTarget as HTMLElement;
        const fieldId = el.dataset.fieldId || '';
        const page = el.dataset.page || '1';
        (window as Window & { __pdfSignableAcroFormMoveResizeFieldId?: string }).__pdfSignableAcroFormMoveResizeFieldId = fieldId;
        window.dispatchEvent(new CustomEvent('pdf-signable-acroform-move-resize', { detail: { fieldId, page } }));
        highlightFieldOnPdf(fieldId, page);
        highlightListRowForMoveResize(fieldId);
      });
    });
    const winMove = window as Window & { __pdfSignableAcroFormMoveResizeFieldId?: string };
    highlightListRowForMoveResize(winMove.__pdfSignableAcroFormMoveResizeFieldId);
  }

  /**
   * Strategy: alternate "Edit config" (modal) vs "Move/Resize" (overlay on PDF). Edit mode is always on while the editor is present.
   * - Only one active at a time: opening one closes the other.
   * - Edit config: click row (no overlay) or click Edit (pencil) → modal; overlay closes if open.
   * - Move/Resize: click Move/Resize on row or click field outline on PDF → overlay; modal closes if open.
   * - Flag __pdfSignableAcroFormMoveResizeFieldId = overlay visible for which field.
   */
  /**
   * Clears move/resize state: closes overlay in viewer and clears __pdfSignableAcroFormMoveResizeFieldId.
   * Call when switching to edit (modal) or when leaving edit mode.
   */
  function clearMoveResizeState(): void {
    (window as Window & { __pdfSignableAcroFormMoveResizeFieldId?: string }).__pdfSignableAcroFormMoveResizeFieldId =
      undefined;
    window.dispatchEvent(new CustomEvent('pdf-signable-acroform-move-resize-close'));
  }

  /** Marks the list row for the given fieldId as "move/resize" (red); removes from others. Pass undefined to clear. */
  function highlightListRowForMoveResize(fieldId: string | undefined): void {
    _fieldsListEl.querySelectorAll('.acroform-list-row').forEach((row) => {
      const el = row as HTMLElement;
      if (fieldId != null && el.dataset.fieldId === fieldId) {
        el.classList.add('acroform-list-row--move-resize');
      } else {
        el.classList.remove('acroform-list-row--move-resize');
      }
    });
  }

  /** Removes red highlight from all field outlines in the widget (back to blue). */
  function clearFieldHighlight(): void {
    const widget = root.closest('.nowo-pdf-signable-widget');
    const scope = widget ?? document;
    scope.querySelectorAll<HTMLElement>('.acroform-field-outline--highlight').forEach((el) => {
      el.classList.remove('acroform-field-outline--highlight');
    });
    _fieldsListEl.querySelectorAll('.acroform-list-row--focused').forEach((el) => {
      el.classList.remove('acroform-list-row--focused');
    });
  }

  /**
   * Only the selected field outline gets the red highlight; all others in the same widget stay blue.
   * Adds .acroform-field-outline--highlight to the outline matching fieldId+page and removes it from every other outline in the widget.
   */
  function highlightFieldOnPdf(fieldId: string, page: string): void {
    const widget = root.closest('.nowo-pdf-signable-widget');
    const scope = widget ?? document;
    const outlines = scope.querySelectorAll<HTMLElement>('.acroform-field-outline');
    let targetOutline: HTMLElement | null = null;
    for (let i = 0; i < outlines.length; i++) {
      const el = outlines[i];
      el.classList.remove('acroform-field-outline--highlight');
      if (el.dataset.fieldId === fieldId) {
        const wrapper = el.closest('.pdf-page-wrapper');
        if (wrapper?.getAttribute('data-page') === page) targetOutline = el;
      }
    }
    if (targetOutline !== null) {
      const el = targetOutline as HTMLElement;
      el.classList.add('acroform-field-outline--highlight');
      const wrapper = el.closest('.pdf-page-wrapper');
      if (wrapper) wrapper.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  }

  _fieldsListEl.addEventListener('click', (e) => {
    const target = e.target as HTMLElement;
    if (target.closest('button') || target.closest('.acroform-row-actions')) return;
    const row = target.closest('.acroform-list-row') as HTMLElement | null;
    if (!row) return;
    const fieldId = String(row.dataset.fieldId ?? '');
    const page = String(row.dataset.page ?? '1');
    highlightFieldOnPdf(fieldId, page);
    const winMove = window as Window & { __pdfSignableAcroFormMoveResizeFieldId?: string };
    if (winMove.__pdfSignableAcroFormMoveResizeFieldId) return;
    const fields = lastLoadedFields.length ? lastLoadedFields : getFieldsFromViewer();
    const field = fields.find((f) => String(f.id) === fieldId);
    if (field) openEditModal(field, draftOverrides[fieldId] ?? {});
  });
  _fieldsListEl.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter' && e.key !== ' ') return;
    const target = e.target as HTMLElement;
    if (target.closest('button') || target.closest('.acroform-row-actions')) return;
    const row = target.closest('.acroform-list-row') as HTMLElement | null;
    if (!row) return;
    e.preventDefault();
    highlightFieldOnPdf(String(row.dataset.fieldId ?? ''), String(row.dataset.page ?? '1'));
  });

  /** When user focuses or clicks an input on the PDF, highlight that field outline (red) and the list row; others stay blue. */
  window.addEventListener('pdf-signable-acroform-field-focused', ((e: CustomEvent<{ fieldId: string; page: number }>) => {
    const detail = e.detail ?? {};
    const fieldId = detail.fieldId;
    const page = detail.page != null ? String(detail.page) : '1';
    if (!fieldId) return;
    highlightFieldOnPdf(fieldId, page);
    _fieldsListEl.querySelectorAll('.acroform-list-row').forEach((row) => {
      const el = row as HTMLElement;
      if (el.dataset.fieldId === fieldId) {
        el.classList.add('acroform-list-row--focused');
        el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      } else {
        el.classList.remove('acroform-list-row--focused');
      }
    });
  }) as EventListener);

  /** Returns the PDF URL from the form input used by the viewer. */
  function getPdfUrlFromForm(): string {
    const input = document.querySelector<HTMLInputElement>('.pdf-url-input');
    return (input?.value ?? '').trim();
  }

  /** Encode ArrayBuffer to base64 in chunks to avoid "Maximum call stack size exceeded" on large PDFs. */
  function arrayBufferToBase64(buffer: ArrayBuffer): string {
    const bytes = new Uint8Array(buffer);
    const chunkSize = 8192;
    let binary = '';
    for (let i = 0; i < bytes.length; i += chunkSize) {
      const chunk = bytes.subarray(i, Math.min(i + chunkSize, bytes.length));
      binary += String.fromCharCode.apply(null, chunk as unknown as number[]);
    }
    return btoa(binary);
  }

  /** Loads overrides from the server (loadUrl) and updates the list and draft. */
  function doLoad(): void {
    const key = (_docKeyEl.value ?? '').trim();
    if (!key) {
      showMessage(str('msg_enter_document_key'), true);
      return;
    }
    const pdfUrl = getPdfUrlFromForm();
    if (!pdfUrl) {
      showMessage(str('msg_load_pdf_first_load'), true);
      return;
    }
    const fieldsFromViewer = getFieldsFromViewer();
    const payload: { document_key: string; pdf_url: string; fields?: AcroFormFieldDescriptor[] } = {
      document_key: key,
      pdf_url: pdfUrl,
    };
    if (fieldsFromViewer.length) payload.fields = fieldsFromViewer;
    _loadBtn.disabled = true;
    fetch(config.loadUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(payload),
    })
      .then((r) => {
        if (!r.ok) return r.json().then((j: { error?: string }) => { throw new Error(j.error ?? r.statusText); });
        return r.json();
      })
      .then((data: { fields?: AcroFormFieldDescriptor[]; overrides?: Record<string, Record<string, unknown>>; fields_extractor_error?: string }) => {
        if (data.fields_extractor_error) {
          showMessage(data.fields_extractor_error, true);
        }
        const fields = data.fields?.length ? data.fields : getFieldsFromViewer();
        draftOverrides = buildFullOverrides(fields, data.overrides ?? {}, getCurrentValuesFromInputs());
        syncDraftToTextarea();
        renderFieldsList(fields, draftOverrides);
        if (!data.fields_extractor_error) {
          const hasStored = data.overrides && Object.keys(data.overrides).length > 0;
          showMessage(
            hasStored ? str('msg_draft_loaded', { count: String(fields.length) }) : str('msg_no_data_server', { count: String(fields.length) }),
            false,
          );
        }
        notifyViewerOverrides();
      })
      .catch((e: Error) => showMessage(str('error_prefix') + e.message, true))
      .finally(() => { _loadBtn.disabled = false; });
  }

  _loadBtn.addEventListener('click', doLoad);

  saveBtn.addEventListener('click', () => {
    const key = (_docKeyEl.value ?? '').trim();
    if (!key) {
      showMessage(str('msg_enter_document_key'), true);
      return;
    }
    if (!syncTextareaToDraft()) {
      showMessage(str('msg_invalid_json'), true);
      return;
    }
    mergeInputsIntoDraft();
    const fields = getFieldsFromViewer();
    const payload = {
      document_key: key,
      overrides: buildFullOverrides(fields, draftOverrides, getCurrentValuesFromInputs()),
      ...(fields.length ? { fields } : {}),
    };
    saveBtn.disabled = true;
    fetch(config.postUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(payload),
    })
      .then((r) => {
        if (!r.ok) return r.json().then((j: { error?: string }) => { throw new Error(j.error ?? r.statusText); });
        return r.json();
      })
      .then(() => {
        showMessage(str('msg_draft_saved'), false);
        notifyViewerOverrides();
      })
      .catch((e: Error) => showMessage(str('error_prefix') + e.message, true))
      .finally(() => { saveBtn.disabled = false; });
  });

  clearBtn.addEventListener('click', () => {
    draftOverrides = {};
    syncDraftToTextarea();
    _fieldsListEl.innerHTML = '';
    _fieldsSection.style.display = 'none';
    showMessage(str('msg_draft_cleared'), false);
    notifyViewerOverrides();
  });

  let applyBtnRef: HTMLButtonElement | null = null;
  if (config.applyUrl) {
    const applyBtn = document.createElement('button');
    applyBtn.id = 'acroform-apply-btn';
    applyBtn.type = 'button';
    applyBtn.className = 'btn btn-sm btn-outline-info';
    applyBtn.textContent = str('btn_apply_pdf');
    applyBtn.title = str('btn_apply_pdf_title');
    clearBtn.after(applyBtn);
    applyBtnRef = applyBtn;
    applyBtn.addEventListener('click', () => {
      const pdfUrl = getPdfUrlFromForm();
      if (!pdfUrl) {
        showMessage(str('msg_apply_pdf_first'), true);
        return;
      }
      mergeInputsIntoDraft();
      const patches = buildPatchesFromOverrides();
      applyBtn.setAttribute('disabled', '');

      /** Fetch PDF from URL, then call apply with pdf_content so the server does not need to fetch (avoids proxy/SSRF issues). */
      const fetchPdfThenApply = (validateOnly: boolean): Promise<ArrayBuffer | null> => {
        if (config.debug) {
          console.debug('[AcroForm apply] request', { validateOnly, patchesCount: patches.length, fieldIds: patches.map((p) => p.fieldId) });
        }
        return fetch(pdfUrl, { method: 'GET', headers: { Accept: 'application/pdf,*/*' } })
          .then((r) => {
            if (!r.ok) throw new Error(`Failed to load PDF: ${r.status}`);
            return r.arrayBuffer();
          })
          .then((pdfBuffer) => {
            const body: { pdf_content: string; patches: AcroFormPatchPayload[]; validate_only?: boolean } = {
              pdf_content: arrayBufferToBase64(pdfBuffer),
              patches,
            };
            if (validateOnly) body.validate_only = true;
            return fetch(config.applyUrl, {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify(body),
            });
          })
          .then((r) => {
            const ct = r.headers.get('Content-Type') ?? '';
            const isJson = ct.includes('application/json');
            if (!r.ok) {
              return r.json().then((j: { error?: string; detail?: string }) => {
                const msg = j.error ?? r.statusText;
                const detail = j.detail ? `\n${j.detail}` : '';
                throw new Error(msg + detail);
              });
            }
            if (validateOnly && isJson) {
              return r.json().then((j: { success?: boolean; error?: string; message?: string; patches_count?: number }) => {
                if (config.debug) {
                  console.debug('[AcroForm apply] response (validate_only)', j);
                }
                if (j.success !== true) {
                  throw new Error(j.error ?? 'Validation failed');
                }
                return null;
              });
            }
            return r.blob().then((blob) => {
              const buf = blob.arrayBuffer();
              if (config.debug) {
                buf.then((ab) => {
                  console.debug('[AcroForm apply] response (PDF)', {
                    contentType: r.headers.get('Content-Type'),
                    size: ab.byteLength,
                    ok: r.ok,
                  });
                });
              }
              return buf;
            });
          });
      };

      const runApply = (): Promise<ArrayBuffer | null> => {
        if (config.debug) {
          return fetchPdfThenApply(true).then(() => fetchPdfThenApply(false));
        }
        return fetchPdfThenApply(false);
      };

      runApply()
        .then((buf) => {
          if (buf) {
            lastAppliedPdf = buf;
            showMessage(str('msg_pdf_modified_received'), false);
            const a = document.createElement('a');
            a.href = URL.createObjectURL(new Blob([buf], { type: 'application/pdf' }));
            a.download = str('download_filename');
            a.click();
            URL.revokeObjectURL(a.href);
          }
        })
        .catch((e: Error) => showMessage(str('error_prefix') + e.message, true))
        .finally(() => applyBtn.removeAttribute('disabled'));
    });
  }

  if (config.processUrl) {
    const processBtn = document.createElement('button');
    processBtn.type = 'button';
    processBtn.className = 'btn btn-sm btn-info';
    processBtn.textContent = str('btn_process');
    processBtn.title = str('btn_process_title');
    (applyBtnRef ?? clearBtn).after(processBtn);
    processBtn.addEventListener('click', () => {
      const pdfToSend = lastAppliedPdf;
      if (!pdfToSend || pdfToSend.byteLength === 0) {
        showMessage(str('msg_process_first'), true);
        return;
      }
      const b64 = arrayBufferToBase64(pdfToSend);
      const key = (_docKeyEl.value ?? '').trim();
      processBtn.setAttribute('disabled', '');
      fetch(config.processUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ pdf_content: b64, document_key: key || undefined }),
      })
        .then((r) => {
          if (!r.ok) return r.json().then((j: { error?: string; detail?: string }) => { throw new Error(j.error ?? j.detail ?? r.statusText); });
          return r.json();
        })
        .then((data: { success?: boolean }) => {
          showMessage(data.success !== false ? str('msg_process_success') : str('msg_processed'), false);
        })
        .catch((e: Error) => showMessage(str('error_prefix') + e.message, true))
        .finally(() => processBtn.removeAttribute('disabled'));
    });
  }

  if (refreshInputsBtn) {
    refreshInputsBtn.addEventListener('click', () => {
      const fields = getFieldsFromViewer();
      if (!fields.length) {
        showMessage(str('msg_refresh_load_first'), true);
        return;
      }
      syncTextareaToDraft();
      mergeInputsIntoDraft();
      draftOverrides = buildFullOverrides(fields, draftOverrides, getCurrentValuesFromInputs());
      syncDraftToTextarea();
      renderFieldsList(fields, draftOverrides);
      showMessage(str('msg_draft_updated'), false);
    });
  }

  _jsonEl.addEventListener('blur', () => {
    if (syncTextareaToDraft()) {
      const fields = lastLoadedFields.length ? lastLoadedFields : getFieldsFromViewer();
      draftOverrides = buildFullOverrides(fields, draftOverrides, getCurrentValuesFromInputs());
      syncDraftToTextarea();
      renderFieldsList(fields, draftOverrides);
    }
  });

  let autoLoadDone = false;
  window.addEventListener('pdf-signable-acroform-fields-updated', () => {
    const key = (_docKeyEl.value ?? '').trim();
    const fields = getFieldsFromViewer();
    if (fields.length) {
      renderFieldsList(fields, draftOverrides);
      if (key && !autoLoadDone) {
        autoLoadDone = true;
        doLoad();
      }
    }
  });

  window.addEventListener('pdf-signable-acroform-move-resize-opened', () => {
    closeEditModal();
  });

  window.addEventListener('pdf-signable-acroform-rect-changed', ((e: CustomEvent<{ fieldId: string; rect: number[] }>) => {
    const { fieldId, rect } = e.detail ?? {};
    if (!fieldId || !Array.isArray(rect) || rect.length < 4) return;
    if (!draftOverrides[fieldId]) draftOverrides[fieldId] = {};
    draftOverrides[fieldId].rect = rect;
    syncDraftToTextarea();
    renderFieldsList(lastLoadedFields.length ? lastLoadedFields : getFieldsFromViewer(), draftOverrides);
    notifyViewerOverrides();
  }) as EventListener);

  window.addEventListener('pdf-signable-acroform-add-field-place', ((e: CustomEvent<{ page: number; llx: number; lly: number; width: number; height: number }>) => {
    const d = e.detail ?? {};
    const { page, llx, lly, width = 100, height = 20 } = d;
    if (typeof page !== 'number' || typeof llx !== 'number' || typeof lly !== 'number') return;
    newFieldCounter += 1;
    const newId = 'new-' + Date.now();
    const urx = llx + width;
    const ury = lly + height;
    const defaultFieldName = str('new_field_name_pattern').replace(/%n/g, String(newFieldCounter));
    draftOverrides[newId] = {
      page,
      rect: [llx, lly, urx, ury],
      controlType: 'text',
      defaultValue: '',
      fieldName: defaultFieldName,
    };
    syncDraftToTextarea();
    notifyViewerOverrides();
    const syntheticField: AcroFormFieldDescriptor = {
      id: newId,
      page,
      rect: [llx, lly, urx, ury],
      width,
      height,
      fieldType: 'Tx',
      value: '',
    };
    setTimeout(() => {
      renderFieldsList(getFieldsFromViewer(), draftOverrides);
    }, 150);
    const w = window as Window & { __pdfSignableAcroFormMoveResizeFieldId?: string };
    if (!w.__pdfSignableAcroFormMoveResizeFieldId) openEditModal(syntheticField, draftOverrides[newId] as Record<string, unknown>);
  }) as EventListener);
}

/** Bootstrap: run editor when #acroform-editor-root is present. */
const root = document.getElementById('acroform-editor-root');
if (root) {
  initAcroFormEditor(root);
}
