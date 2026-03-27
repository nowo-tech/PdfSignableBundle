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

describe('shared/touch', () => {
  it('no envuelve si container es null', () => {
    const controller = createTouchController(null);
    const canvasWrapper = document.createElement('div');

    controller.ensureWrapper(canvasWrapper);

    expect(controller.getWrapper()).toBeNull();
    expect(controller.getScale()).toBe(1);
  });

  it('envuelve, procesa pinch y permite reset', () => {
    const container = document.createElement('div');
    const canvasWrapper = document.createElement('div');
    container.appendChild(canvasWrapper);
    const controller = createTouchController(container);

    controller.ensureWrapper(canvasWrapper);
    const wrapper = controller.getWrapper() as HTMLElement;
    expect(wrapper).not.toBeNull();
    expect(wrapper.id).toBe('pdf-touch-wrapper');

    wrapper.dispatchEvent(touchEvent('touchstart', { x: 0, y: 0 }, { x: 10, y: 0 }));
    wrapper.dispatchEvent(touchEvent('touchmove', { x: 0, y: 0 }, { x: 20, y: 0 }));

    expect(controller.getScale()).toBeGreaterThan(1);
    expect(controller.getTranslate().x).toBeGreaterThanOrEqual(0);

    controller.reset();
    expect(controller.getScale()).toBe(1);
    expect(controller.getTranslate()).toEqual({ x: 0, y: 0 });
  });
});
