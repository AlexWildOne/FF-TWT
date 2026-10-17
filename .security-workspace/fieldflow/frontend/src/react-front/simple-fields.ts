function fieldFlowSimpleFieldInput(props: FieldFlowSimpleFieldProps): HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement | null {
  const element = document.getElementById(props.inputId);
  if (element instanceof HTMLInputElement || element instanceof HTMLTextAreaElement || element instanceof HTMLSelectElement) return element;
  return null;
}

function fieldFlowSimpleFieldNativeSurface(input: HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement): HTMLElement {
  const group = input.closest('.routespro-form-input-group');
  return group instanceof HTMLElement ? group : input;
}

function fieldFlowSimpleFieldSnapshot(props: FieldFlowSimpleFieldProps): FieldFlowSimpleFieldSnapshot {
  const input = fieldFlowSimpleFieldInput(props);
  const raw = String(input?.value || '');
  let valueLabel = raw.trim();
  const options: FieldFlowSimpleFieldOption[] = [];
  if (input instanceof HTMLSelectElement) {
    Array.from(input.options).forEach((option) => options.push({ value: option.value, label: String(option.textContent || option.value) }));
    if (input.selectedIndex >= 0) valueLabel = String(input.options[input.selectedIndex]?.textContent || raw).trim();
  }
  return {
    value: raw,
    empty: raw.trim() === '',
    valueLabel,
    disabled: Boolean(input?.disabled),
    readOnly: input instanceof HTMLInputElement || input instanceof HTMLTextAreaElement ? Boolean(input.readOnly) : false,
    min: input instanceof HTMLInputElement ? String(input.min || '') : '',
    max: input instanceof HTMLInputElement ? String(input.max || '') : '',
    step: input instanceof HTMLInputElement ? String(input.step || '') : '',
    options,
  };
}

function fieldFlowSimpleFieldWrite(props: FieldFlowSimpleFieldProps, value: string): void {
  const input = fieldFlowSimpleFieldInput(props);
  if (!input) return;
  input.value = value;
  input.dispatchEvent(new Event('input', { bubbles: true }));
  input.dispatchEvent(new Event('change', { bubbles: true }));
}

function fieldFlowSimpleFieldControlType(props: FieldFlowSimpleFieldProps): string {
  if (props.fieldType === 'currency' || props.fieldType === 'percent') return 'number';
  if (props.fieldType === 'number' || props.fieldType === 'date' || props.fieldType === 'time') return props.fieldType;
  return 'text';
}

function FieldFlowSimpleFieldAssistIsland(props: FieldFlowSimpleFieldProps & { root: Element }): unknown {
  const { root } = props;
  const element = window.wp?.element;
  if (!element) return null;
  const [snapshot, setSnapshot] = element.useState<FieldFlowSimpleFieldSnapshot>(fieldFlowSimpleFieldSnapshot(props));
  element.useEffect(() => {
    const input = fieldFlowSimpleFieldInput(props);
    const field = root.closest('.routespro-form-field');
    const refresh = (): void => setSnapshot(fieldFlowSimpleFieldSnapshot(props));
    if (!input) return undefined;
    const surface = fieldFlowSimpleFieldNativeSurface(input);
    surface.setAttribute('data-fieldflow-react-simple-native', '1');
    field?.setAttribute('data-fieldflow-react-simple-control', '1');
    const redirectFocus = (): void => {
      const control = root.querySelector('[data-fieldflow-react-simple-control-input]') as HTMLElement | null;
      control?.focus();
    };
    const redirectInvalid = (event: Event): void => {
      event.preventDefault();
      const control = root.querySelector('[data-fieldflow-react-simple-control-input]') as HTMLElement | null;
      control?.focus();
      root.setAttribute('data-fieldflow-react-simple-invalid', '1');
    };
    const clearInvalid = (): void => root.removeAttribute('data-fieldflow-react-simple-invalid');
    input.addEventListener('input', refresh);
    input.addEventListener('change', refresh);
    input.addEventListener('blur', refresh);
    input.addEventListener('focus', redirectFocus);
    input.addEventListener('invalid', redirectInvalid);
    input.addEventListener('input', clearInvalid);
    const observer = new MutationObserver(refresh);
    observer.observe(input, { attributes: true, attributeFilter: ['disabled', 'readonly', 'value', 'min', 'max', 'step'] });
    field?.addEventListener('fieldflow:form-state', refresh as EventListener);
    document.addEventListener('fieldflow:forms-runtime-v3-snapshot', refresh as EventListener);
    document.addEventListener('fieldflow:offline-form-synced', refresh as EventListener);
    document.addEventListener('fieldflow:sync-completed', refresh as EventListener);
    return () => {
      input.removeEventListener('input', refresh);
      input.removeEventListener('change', refresh);
      input.removeEventListener('blur', refresh);
      input.removeEventListener('focus', redirectFocus);
      input.removeEventListener('invalid', redirectInvalid);
      input.removeEventListener('input', clearInvalid);
      observer.disconnect();
      field?.removeEventListener('fieldflow:form-state', refresh as EventListener);
      document.removeEventListener('fieldflow:forms-runtime-v3-snapshot', refresh as EventListener);
      document.removeEventListener('fieldflow:offline-form-synced', refresh as EventListener);
      document.removeEventListener('fieldflow:sync-completed', refresh as EventListener);
      surface.removeAttribute('data-fieldflow-react-simple-native');
      field?.removeAttribute('data-fieldflow-react-simple-control');
      root.removeAttribute('data-fieldflow-react-simple-invalid');
    };
  }, [props.inputId]);

  const status = snapshot.disabled ? 'Bloqueado' : (snapshot.empty ? (props.required ? 'Por preencher' : 'Opcional') : 'Preenchido');
  const className = snapshot.disabled ? 'is-disabled' : (snapshot.empty ? (props.required ? 'is-required' : 'is-optional') : 'is-filled');
  const commonProps: Record<string, unknown> = {
    'data-fieldflow-react-simple-control-input': '1',
    'aria-label': props.label || props.fieldKey,
    'aria-required': props.required ? 'true' : 'false',
    value: snapshot.value,
    disabled: snapshot.disabled,
    readOnly: snapshot.readOnly,
    onChange: (event: unknown) => fieldFlowSimpleFieldWrite(props, String((event as { target?: { value?: string } }).target?.value || '')),
    onBlur: () => {
      const input = fieldFlowSimpleFieldInput(props);
      input?.dispatchEvent(new Event('blur'));
    },
  };

  let control: unknown;
  if (props.fieldType === 'textarea') {
    control = element.createElement('textarea', { ...commonProps, rows: 4 });
  } else if (props.fieldType === 'select') {
    control = element.createElement('select', commonProps,
      ...snapshot.options.map((option) => element.createElement('option', { key: option.value, value: option.value }, option.label))
    );
  } else {
    const inputProps: Record<string, unknown> = { ...commonProps, type: fieldFlowSimpleFieldControlType(props) };
    if (snapshot.min !== '') inputProps.min = snapshot.min;
    if (snapshot.max !== '') inputProps.max = snapshot.max;
    if (snapshot.step !== '') inputProps.step = snapshot.step;
    control = element.createElement('input', inputProps);
  }

  const prefix = props.fieldType === 'currency' ? '€' : '';
  const suffix = props.fieldType === 'percent' ? '%' : String(props.unit || '');

  return element.createElement('div', {
    className: 'ff-react-front-simple-control ' + className,
    'data-react-contract': FIELD_FLOW_REACT_SIMPLE_FIELD_CONTROL_CONTRACT,
  },
  element.createElement('div', { className: 'ff-react-front-simple-control-input' },
    prefix ? element.createElement('span', { className: 'ff-react-front-simple-addon' }, prefix) : null,
    control,
    suffix ? element.createElement('span', { className: 'ff-react-front-simple-addon' }, suffix) : null
  ),
  element.createElement('div', { className: 'ff-react-front-field-assist', 'data-react-contract': FIELD_FLOW_REACT_SIMPLE_FIELD_CONTRACT },
    element.createElement('span', null, props.fieldType === 'textarea' ? 'Texto longo' : props.fieldType),
    element.createElement('strong', null, status),
    (!snapshot.empty && snapshot.valueLabel && props.fieldType === 'select') ? element.createElement('small', { title: snapshot.valueLabel }, snapshot.valueLabel) : null
  ));
}
