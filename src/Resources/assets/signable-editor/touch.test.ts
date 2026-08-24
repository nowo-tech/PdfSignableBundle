import { describe, expect, it } from 'vitest';

import { createTouchController } from './touch';

function touchEvent(type: string, a: { x: number; y: number }, b: { x: number; y: number }): Event {
  const ev = new Event(type, { bubbles: true, cancelable: true });
  Object.defineProperty(ev, 'touches', {
    value: [
      { clientX: a.x, clientY: a.y },
      { clientX: b.x, clientY: b.y },
    ],
  });
  return ev;
}

describe('signable-editor/touch', () => {
  it('does not wrap when container is null', () => {
    const controller = createTouchController(null);
    const canvasWrapper = document.createElement('div');
    controller.ensureWrapper(canvasWrapper);
    expect(controller.getWrapper()).toBeNull();
    controller.reset();
    expect(controller.getScale()).toBe(1);
    expect(controller.getTranslate()).toEqual({ x: 0, y: 0 });
  });

  it('ensureWrapper es idempotente', () => {
    const container = document.createElement('div');
    const canvasWrapper = document.createElement('div');
    container.appendChild(canvasWrapper);
    const controller = createTouchController(container);

    controller.ensureWrapper(canvasWrapper);
    const wrapper1 = controller.getWrapper();
    controller.ensureWrapper(canvasWrapper);
    const wrapper2 = controller.getWrapper();

    expect(wrapper1).toBe(wrapper2);
  });

  it('actualiza transform con gesto de pinch', () => {
    const container = document.createElement('div');
    const canvasWrapper = document.createElement('div');
    container.appendChild(canvasWrapper);
    const controller = createTouchController(container);
    controller.ensureWrapper(canvasWrapper);
    const wrapper = controller.getWrapper() as HTMLElement;

    wrapper.dispatchEvent(touchEvent('touchstart', { x: 20, y: 20 }, { x: 60, y: 20 }));
    wrapper.dispatchEvent(touchEvent('touchmove', { x: 40, y: 40 }, { x: 120, y: 40 }));

    expect(controller.getScale()).toBeGreaterThan(1);
    expect(wrapper.style.transform).toContain('scale(');

    const single = (type: string): Event => {
      const ev = new Event(type, { bubbles: true, cancelable: true });
      Object.defineProperty(ev, 'touches', { value: [{ clientX: 1, clientY: 1 }] });
      return ev;
    };
    wrapper.dispatchEvent(single('touchstart'));
    wrapper.dispatchEvent(single('touchmove'));

    controller.reset();
    expect(controller.getScale()).toBe(1);
    expect(controller.getTranslate()).toEqual({ x: 0, y: 0 });
  });
});

