function fieldFlowChoiceInputs(root: Element, props: FieldFlowChoiceFieldProps): HTMLInputElement[] {
  const field = root.closest('.routespro-form-field');
  if (!field) return [];
  return Array.from(field.querySelectorAll('input[type="radio"],input[type="checkbox"]'))
    .filter((node): node is HTMLInputElement => node instanceof HTMLInputElement)
    .filter((input) => input.name === props.fieldKey || input.name === `${props.fieldKey}[]`);
}

function fieldFlowChoiceSnapshot(root: Element, props: FieldFlowChoiceFieldProps): FieldFlowChoiceSnapshot {
  const inputs = fieldFlowChoiceInputs(root, props);
  const selected = inputs.filter((input) => input.checked).map((input) => String(input.value || '1'));
  return {
    selected,
    disabled: inputs.length > 0 && inputs.every((input) => input.disabled),
  };
}

function fieldFlowChoiceWrite(root: Element, props: FieldFlowChoiceFieldProps, value: string): void {
  const inputs = fieldFlowChoiceInputs(root, props);
  if (!inputs.length) return;
  const booleanMode = props.fieldType === 'checkbox' && !props.multiple && props.options.length <= 1;
  if (booleanMode) {
    const target = inputs[0];
    target.checked = !target.checked;
    target.dispatchEvent(new Event('input', { bubbles: true }));
    target.dispatchEvent(new Event('change', { bubbles: true }));
    return;
  }
  if (props.multiple) {
    const target = inputs.find((input) => String(input.value) === value);
    if (!target) return;
    target.checked = !target.checked;
    target.dispatchEvent(new Event('input', { bubbles: true }));
    target.dispatchEvent(new Event('change', { bubbles: true }));
    return;
  }
  inputs.forEach((input) => {
    const next = String(input.value) === value;
    const changed = input.checked !== next;
    input.checked = next;
    if (changed) {
      input.dispatchEvent(new Event('input', { bubbles: true }));
      input.dispatchEvent(new Event('change', { bubbles: true }));
    }
  });
}

function FieldFlowChoiceFieldIsland(props: FieldFlowChoiceFieldProps & { root: Element }): unknown {
  const { root } = props;
  const element = window.wp?.element;
  if (!element) return null;
  const [snapshot, setSnapshot] = element.useState<FieldFlowChoiceSnapshot>(fieldFlowChoiceSnapshot(root, props));
  element.useEffect(() => {
    const field = root.closest('.routespro-form-field');
    const inputs = fieldFlowChoiceInputs(root, props);
    const native = field?.querySelector('.routespro-form-fieldset, .routespro-form-check') as HTMLElement | null;
    const refresh = (): void => setSnapshot(fieldFlowChoiceSnapshot(root, props));
    native?.setAttribute('data-fieldflow-react-choice-native', '1');
    field?.setAttribute('data-fieldflow-react-choice-control', '1');
    const invalidHandlers: Array<[HTMLInputElement, EventListener]> = [];
    inputs.forEach((input) => {
      input.addEventListener('input', refresh);
      input.addEventListener('change', refresh);
      const invalidHandler: EventListener = (event) => {
        event.preventDefault();
        root.setAttribute('data-fieldflow-react-choice-invalid', '1');
        const value = String(input.value || '1');
        const button = Array.from(root.querySelectorAll('[data-fieldflow-react-choice-value]')).find((node) => node.getAttribute('data-fieldflow-react-choice-value') === value) as HTMLElement | undefined;
        button?.focus();
      };
      input.addEventListener('invalid', invalidHandler);
      invalidHandlers.push([input, invalidHandler]);
    });
    const clearInvalid = (): void => root.removeAttribute('data-fieldflow-react-choice-invalid');
    root.addEventListener('click', clearInvalid);
    field?.addEventListener('fieldflow:form-state', refresh as EventListener);
    document.addEventListener('fieldflow:forms-runtime-v3-snapshot', refresh as EventListener);
    document.addEventListener('fieldflow:offline-form-synced', refresh as EventListener);
    const observer = new MutationObserver(refresh);
    inputs.forEach((input) => observer.observe(input, { attributes: true, attributeFilter: ['checked', 'disabled', 'required'] }));
    return () => {
      inputs.forEach((input) => {
        input.removeEventListener('input', refresh);
        input.removeEventListener('change', refresh);
      });
      invalidHandlers.forEach(([input, handler]) => input.removeEventListener('invalid', handler));
      root.removeEventListener('click', clearInvalid);
      field?.removeEventListener('fieldflow:form-state', refresh as EventListener);
      document.removeEventListener('fieldflow:forms-runtime-v3-snapshot', refresh as EventListener);
      document.removeEventListener('fieldflow:offline-form-synced', refresh as EventListener);
      observer.disconnect();
      native?.removeAttribute('data-fieldflow-react-choice-native');
      field?.removeAttribute('data-fieldflow-react-choice-control');
      root.removeAttribute('data-fieldflow-react-choice-invalid');
    };
  }, [props.fieldKey, props.fieldType]);

  const booleanMode = props.fieldType === 'checkbox' && !props.multiple && props.options.length <= 1;
  if (booleanMode) {
    const active = snapshot.selected.length > 0;
    return element.createElement('button', {
      type: 'button',
      className: 'ff-react-choice-toggle ' + (active ? 'is-selected' : ''),
      'aria-pressed': active ? 'true' : 'false',
      'data-fieldflow-react-choice-value': '1',
      'data-react-contract': FIELD_FLOW_REACT_CHOICE_FIELD_CONTRACT,
      disabled: snapshot.disabled,
      onClick: () => fieldFlowChoiceWrite(root, props, '1'),
    },
    element.createElement('span', { className: 'ff-react-choice-toggle-indicator', 'aria-hidden': 'true' }),
    element.createElement('span', null,
      element.createElement('strong', null, props.label),
      element.createElement('small', null, active ? 'Sim' : (props.required ? 'Seleciona para confirmar' : 'Não'))
    ));
  }

  const options = props.options.map((option) => {
    const selected = snapshot.selected.includes(option.value);
    return element.createElement('button', {
      type: 'button',
      key: option.value,
      className: 'ff-react-choice-option ' + (selected ? 'is-selected' : '') + (props.fieldType === 'visual_choice' ? ' is-visual' : ''),
      'aria-pressed': selected ? 'true' : 'false',
      'data-fieldflow-react-choice-value': option.value,
      disabled: snapshot.disabled,
      onClick: () => fieldFlowChoiceWrite(root, props, option.value),
    },
    option.imageUrl ? element.createElement('span', { className: 'ff-react-choice-media' }, element.createElement('img', { src: option.imageUrl, alt: '', loading: 'lazy' })) : null,
    element.createElement('span', { className: 'ff-react-choice-copy' },
      element.createElement('strong', null, option.label),
      element.createElement('small', null, selected ? 'Selecionado' : (props.multiple ? 'Tocar para selecionar' : 'Selecionar'))
    ),
    element.createElement('span', { className: 'ff-react-choice-check', 'aria-hidden': 'true' }, selected ? '✓' : ''));
  });

  return element.createElement('section', {
    className: 'ff-react-choice-control ' + (props.fieldType === 'visual_choice' ? 'is-visual-grid' : ''),
    'data-react-contract': FIELD_FLOW_REACT_CHOICE_FIELD_CONTRACT,
  },
  element.createElement('div', { className: 'ff-react-choice-head' },
    element.createElement('strong', null, props.label + (props.required ? ' *' : '')),
    element.createElement('span', null, props.multiple ? `${snapshot.selected.length} selecionada(s)` : (snapshot.selected.length ? 'Selecionado' : 'Por selecionar'))
  ),
  element.createElement('div', { className: 'ff-react-choice-grid' }, ...options));
}
