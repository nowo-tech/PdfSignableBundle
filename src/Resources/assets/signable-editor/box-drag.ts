/**
 * @fileoverview Signature box overlay drag, resize and rotate on the PDF canvas.
 * Handles mousedown on overlays and resize/rotate handles; updates form inputs and calls back to refresh overlays.
 */
if (typeof console !== 'undefined') console.debug('[PdfSignable] loaded: signable-editor/box-drag.ts');
import type { PDFViewport, BoxBounds } from './types';

/** Context for box drag/resize/rotate. All refs and helpers provided by the caller. */
export interface BoxDragContext {
  canvasWrapper: HTMLElement;
  boxesList: HTMLElement;
  boxItemSelector: string;
  pageViewports: Record<number, PDFViewport>;
  getPageField: (container: Element) => HTMLInputElement | HTMLSelectElement | null;
  getSelectedUnit: () => string;
  getSelectedOrigin: () => string;
  formToViewport: (
    viewport: PDFViewport,
    xPt: number,
    yPt: number,
    wPt: number,
    hPt: number,
    origin: string
  ) => { vpX: number; vpY: number };
  viewportToForm: (
    viewport: PDFViewport,
    vpLeft: number,
    vpTop: number,
    wPt: number,
    hPt: number,
    origin: string
  ) => { xPt: number; yPt: number };
  unitToPt: (val: number, unit: string) => number;
  ptToUnit: (val: number, unit: string) => number;
  getTouchScale: () => number;
  onOverlaysUpdate: () => void;
  setIsDragging: (value: boolean) => void;
  preventBoxOverlap: boolean;
  snapGrid: number;
  snapToBoxes: boolean;
  SNAP_THRESHOLD_PX: number;
  lockBoxDimensions: boolean;
  enableRotation: boolean;
  /** Minimum width in form unit (0 = no minimum). Enforced when resizing. */
  minBoxWidthForm: number;
  /** Minimum height in form unit (0 = no minimum). Enforced when resizing. */
  minBoxHeightForm: number;
  noOverlapMessage: string;
  debugLog: (...args: unknown[]) => void;
  debugWarn: (...args: unknown[]) => void;
}

/** Axis-aligned bounding box size for a rectangle of size (w, h) rotated by angleDeg (degrees). */
export function getRotatedAabbSize(w: number, h: number, angleDeg: number): { aabbW: number; aabbH: number } {
  const rad = (angleDeg * Math.PI) / 180;
  const cos = Math.abs(Math.cos(rad));
  const sin = Math.abs(Math.sin(rad));
  return {
    aabbW: w * cos + h * sin,
    aabbH: w * sin + h * cos,
  };
}

/** Returns true if two boxes on the same page have overlapping rectangles (in form units). */
export function boxesOverlap(a: BoxBounds, b: BoxBounds): boolean {
  if (a.page !== b.page) return false;
  const ax2 = a.x + a.w;
  const bx2 = b.x + b.w;
  const ay2 = a.y + a.h;
  const by2 = b.y + b.h;
  return a.x < bx2 && b.x < ax2 && a.y < by2 && b.y < ay2;
}

/**
 * Sets up drag/resize/rotate for signature box overlays and returns setSelectedBoxIndex / getSelectedBoxIndex.
 * Call after overlays are created; attaches a single mousedown listener to canvasWrapper.
 *
 * @param ctx - Context with DOM refs, getters, callbacks and options
 * @returns Object with setSelectedBoxIndex and getSelectedBoxIndex (for overlay highlight)
 */
export function setupBoxDragResizeRotate(ctx: BoxDragContext): {
  setSelectedBoxIndex: (idx: number | null) => void;
  getSelectedBoxIndex: () => number | null;
} {
  const {
    canvasWrapper,
    boxesList,
    boxItemSelector,
    pageViewports,
    getPageField,
    getSelectedUnit,
    getSelectedOrigin,
    formToViewport: formToVp,
    viewportToForm: vpToForm,
    unitToPt,
    ptToUnit,
    getTouchScale,
    onOverlaysUpdate,
    setIsDragging,
    preventBoxOverlap,
    snapGrid,
    snapToBoxes,
    SNAP_THRESHOLD_PX,
    lockBoxDimensions,
    enableRotation,
    minBoxWidthForm,
    minBoxHeightForm,
    noOverlapMessage,
    debugLog,
    debugWarn,
  } = ctx;

  function getBoxesFromForm(): BoxBounds[] {
    const items = boxesList.querySelectorAll<HTMLElement>(boxItemSelector);
    const result: BoxBounds[] = [];
    items.forEach((item) => {
      const page = parseInt(getPageField(item)?.value ?? '1', 10);
      const x = parseFloat(item.querySelector<HTMLInputElement>('[data-pdf-signable="x"]')?.value ?? '0');
      const y = parseFloat(item.querySelector<HTMLInputElement>('[data-pdf-signable="y"]')?.value ?? '0');
      const w = parseFloat(item.querySelector<HTMLInputElement>('[data-pdf-signable="width"]')?.value ?? '150');
      const h = parseFloat(item.querySelector<HTMLInputElement>('[data-pdf-signable="height"]')?.value ?? '40');
      result.push({ page, x, y, w, h });
    });
    return result;
  }

  function getOtherBoxesViewportBounds(
    pageNum: number,
    excludeIndex: number
  ): { left: number; top: number; right: number; bottom: number }[] {
    const vp = pageViewports[pageNum];
    if (!vp) return [];
    const unit = getSelectedUnit();
    const origin = getSelectedOrigin();
    const s = vp.scale || 1.5;
    const boxes = getBoxesFromForm();
    const out: { left: number; top: number; right: number; bottom: number }[] = [];
    boxes.forEach((b, i) => {
      if (i === excludeIndex || b.page !== pageNum) return;
      const xPt = unitToPt(b.x, unit);
      const yPt = unitToPt(b.y, unit);
      const wPt = unitToPt(b.w, unit);
      const hPt = unitToPt(b.h, unit);
      const { vpX, vpY } = formToVp(vp, xPt, yPt, wPt, hPt, origin);
      out.push({
        left: vpX,
        top: vpY,
        right: vpX + wPt * s,
        bottom: vpY + hPt * s,
      });
    });
    return out;
  }

  let selectedBoxIndex: number | null = null;

  function setSelectedBoxIndex(idx: number | null): void {
    selectedBoxIndex = idx;
    canvasWrapper.querySelectorAll('[data-pdf-signable="overlay"].selected').forEach((el) =>
      el.classList.remove('selected')
    );
    if (idx !== null) {
      const overlay = canvasWrapper.querySelector(`[data-pdf-signable="overlay"][data-box-index="${idx}"]`);
      if (overlay) overlay.classList.add('selected');
    }
  }

  interface DragState {
    mode: 'move' | 'resize';
    handle: string | null;
    overlay: HTMLElement;
    item: HTMLElement;
    boxIndex: number;
    viewport: PDFViewport;
    startX: number;
    startY: number;
    startLeft: number;
    startTop: number;
    startRight: number;
    startBottom: number;
    startFormX: number;
    startFormY: number;
    startFormW: number;
    startFormH: number;
  }
  let dragState: DragState | null = null;

  interface RotateState {
    overlay: HTMLElement;
    item: HTMLElement;
    centerX: number;
    centerY: number;
    startAngle: number;
    startMouseAngle: number;
  }
  let rotateState: RotateState | null = null;

  function onRotateMove(e: MouseEvent): void {
    if (!rotateState) return;
    const { overlay, item, centerX, centerY, startAngle, startMouseAngle } = rotateState;
    const currentMouseAngle = Math.atan2(e.clientY - centerY, e.clientX - centerX);
    let deltaDeg = ((currentMouseAngle - startMouseAngle) * 180) / Math.PI;
    let newAngle = startAngle + deltaDeg;
    while (newAngle > 180) newAngle -= 360;
    while (newAngle < -180) newAngle += 360;
    const angleInp = item.querySelector<HTMLInputElement>('[data-pdf-signable="angle"]');
    if (angleInp) angleInp.value = String(Math.round(newAngle * 100) / 100);
    overlay.style.transform = `rotate(${newAngle}deg)`;
  }

  function onRotateEnd(): void {
    if (!rotateState) return;
    document.removeEventListener('mousemove', onRotateMove);
    document.removeEventListener('mouseup', onRotateEnd);
    rotateState = null;
  }

  function onOverlayMouseDown(e: MouseEvent): void {
    const overlay = (e.target as HTMLElement).closest('[data-pdf-signable="overlay"]') as HTMLElement | null;
    if (!overlay?.dataset?.boxIndex) return;
    e.preventDefault();
    e.stopPropagation();

    const rotateHandle = (e.target as HTMLElement).closest('.rotate-handle');
    if (enableRotation && rotateHandle) {
      const boxIndex = parseInt(overlay.dataset.boxIndex, 10);
      const item = boxesList.querySelectorAll<HTMLElement>(boxItemSelector)[boxIndex];
      if (!item) return;
      const angleInp = item.querySelector<HTMLInputElement>('[data-pdf-signable="angle"]');
      if (!angleInp) return;
      const rect = overlay.getBoundingClientRect();
      const centerX = rect.left + rect.width / 2;
      const centerY = rect.top + rect.height / 2;
      const startAngle = parseFloat(angleInp.value) || 0;
      const startMouseAngle = Math.atan2(e.clientY - centerY, e.clientX - centerX);
      rotateState = { overlay, item, centerX, centerY, startAngle, startMouseAngle };
      document.addEventListener('mousemove', onRotateMove);
      document.addEventListener('mouseup', onRotateEnd);
      return;
    }

    const handleEl = (e.target as HTMLElement).closest('.resize-handle');
    if (handleEl && lockBoxDimensions) return;
    const boxIndex = parseInt(overlay.dataset.boxIndex, 10);
    const item = boxesList.querySelectorAll<HTMLElement>(boxItemSelector)[boxIndex];
    if (!item) return;
    const wrapper = overlay.closest('.pdf-page-wrapper') as HTMLElement | null;
    const pageNum = parseInt(wrapper?.dataset?.page ?? '1', 10);
    const viewport = pageViewports[pageNum];
    if (!viewport) return;
    const overlayW = parseFloat(overlay.style.width) || 20;
    const overlayH = parseFloat(overlay.style.height) || 14;
    const left = parseFloat(overlay.style.left) || 0;
    const top = parseFloat(overlay.style.top) || 0;
    const xIn = item.querySelector<HTMLInputElement>('[data-pdf-signable="x"]');
    const yIn = item.querySelector<HTMLInputElement>('[data-pdf-signable="y"]');
    const wIn = item.querySelector<HTMLInputElement>('[data-pdf-signable="width"]');
    const hIn = item.querySelector<HTMLInputElement>('[data-pdf-signable="height"]');
    dragState = {
      mode: handleEl ? 'resize' : 'move',
      handle: handleEl ? (handleEl as HTMLElement).dataset.handle ?? null : null,
      overlay,
      item,
      boxIndex,
      viewport,
      startX: e.clientX,
      startY: e.clientY,
      startLeft: left,
      startTop: top,
      startRight: left + overlayW,
      startBottom: top + overlayH,
      startFormX: xIn ? parseFloat(xIn.value) || 0 : 0,
      startFormY: yIn ? parseFloat(yIn.value) || 0 : 0,
      startFormW: wIn ? parseFloat(wIn.value) || 150 : 150,
      startFormH: hIn ? parseFloat(hIn.value) || 40 : 40,
    };
    overlay.classList.add('dragging');
    setIsDragging(true);
    if (dragState.mode === 'move') setSelectedBoxIndex(boxIndex);
    document.addEventListener('mousemove', onDragMove);
    document.addEventListener('mouseup', onDragEnd);
  }

  function onDragMove(e: MouseEvent): void {
    if (!dragState) return;
    const { overlay: o, viewport: vp, startLeft: sl, startTop: st, startRight: sr, startBottom: sb } = dragState;
    const dx = (e.clientX - dragState.startX) / getTouchScale();
    const dy = (e.clientY - dragState.startY) / getTouchScale();
    const minSize = 20;
    let newLeft: number, newTop: number, newW: number, newH: number;

    if (dragState.mode === 'move') {
      const moveW = sr - sl;
      const moveH = sb - st;
      const angleInp = dragState.item.querySelector<HTMLInputElement>('[data-pdf-signable="angle"]');
      const angleDeg = enableRotation && angleInp ? parseFloat(angleInp.value) || 0 : 0;
      const { aabbW, aabbH } = getRotatedAabbSize(moveW, moveH, angleDeg);
      const leftMin = aabbW / 2 - moveW / 2;
      const leftMax = vp.width - aabbW / 2 - moveW / 2;
      const topMin = aabbH / 2 - moveH / 2;
      const topMax = vp.height - aabbH / 2 - moveH / 2;
      newLeft = Math.max(leftMin, Math.min(leftMax, sl + dx));
      newTop = Math.max(topMin, Math.min(topMax, st + dy));
      newW = moveW;
      newH = moveH;
    } else {
      const h = dragState.handle;
      let left = sl;
      let top = st;
      let right = sr;
      let bottom = sb;
      if (h === 'se') {
        right = Math.min(vp.width, Math.max(sl + minSize, sr + dx));
        bottom = Math.min(vp.height, Math.max(st + minSize, sb + dy));
      } else if (h === 'sw') {
        left = Math.max(0, Math.min(sr - minSize, sl + dx));
        bottom = Math.min(vp.height, Math.max(st + minSize, sb + dy));
      } else if (h === 'ne') {
        right = Math.min(vp.width, Math.max(sl + minSize, sr + dx));
        top = Math.max(0, Math.min(sb - minSize, st + dy));
      } else if (h === 'nw') {
        left = Math.max(0, Math.min(sr - minSize, sl + dx));
        top = Math.max(0, Math.min(sb - minSize, st + dy));
      }
      newLeft = left;
      newTop = top;
      newW = right - left;
      newH = bottom - top;
    }

    const s = vp.scale || 1.5;
    const pageNum = parseInt((o.closest('.pdf-page-wrapper') as HTMLElement)?.dataset?.page ?? '1', 10);

    if (snapGrid > 0) {
      const wPt = newW / s;
      const hPt = newH / s;
      const coord = vpToForm(vp, newLeft, newTop, wPt, hPt, getSelectedOrigin());
      const unit = getSelectedUnit();
      let xForm = ptToUnit(coord.xPt, unit);
      let yForm = ptToUnit(coord.yPt, unit);
      let wForm = ptToUnit(wPt, unit);
      let hForm = ptToUnit(hPt, unit);
      xForm = Math.round(xForm / snapGrid) * snapGrid;
      yForm = Math.round(yForm / snapGrid) * snapGrid;
      wForm = Math.round(wForm / snapGrid) * snapGrid;
      hForm = Math.max(snapGrid, Math.round(hForm / snapGrid) * snapGrid);
      const xPt = unitToPt(xForm, unit);
      const yPt = unitToPt(yForm, unit);
      const nwPt = unitToPt(wForm, unit);
      const nhPt = unitToPt(hForm, unit);
      const snapped = formToVp(vp, xPt, yPt, nwPt, nhPt, getSelectedOrigin());
      newLeft = snapped.vpX;
      newTop = snapped.vpY;
      newW = nwPt * s;
      newH = nhPt * s;
    }

    if (snapToBoxes) {
      const other = getOtherBoxesViewportBounds(pageNum, dragState.boxIndex);
      const newRight = newLeft + newW;
      const newBottom = newTop + newH;
      const allX = other.flatMap((b) => [b.left, b.right]);
      const allY = other.flatMap((b) => [b.top, b.bottom]);
      const snapEdge = (val: number, targets: number[]): number => {
        let best = val;
        let bestDist = SNAP_THRESHOLD_PX;
        for (const t of targets) {
          const d = Math.abs(val - t);
          if (d < bestDist) {
            bestDist = d;
            best = t;
          }
        }
        return best;
      };
      const snappedLeft = snapEdge(newLeft, allX);
      const snappedRight = snapEdge(newRight, allX);
      const snappedTop = snapEdge(newTop, allY);
      const snappedBottom = snapEdge(newBottom, allY);
      newLeft = snappedLeft;
      newTop = snappedTop;
      newW = Math.max(minSize, snappedRight - snappedLeft);
      newH = Math.max(minSize, snappedBottom - snappedTop);
    }

    o.style.left = newLeft + 'px';
    o.style.top = newTop + 'px';
    o.style.width = newW + 'px';
    o.style.height = newH + 'px';

    let wPt = newW / s;
    let hPt = newH / s;
    const coord = vpToForm(vp, newLeft, newTop, wPt, hPt, getSelectedOrigin());
    const unit = getSelectedUnit();
    let wForm = ptToUnit(wPt, unit);
    let hForm = ptToUnit(hPt, unit);
    if (minBoxWidthForm > 0) wForm = Math.max(wForm, minBoxWidthForm);
    if (minBoxHeightForm > 0) hForm = Math.max(hForm, minBoxHeightForm);
    wPt = unitToPt(wForm, unit);
    hPt = unitToPt(hForm, unit);
    const clampedWpx = wPt * s;
    const clampedHpx = hPt * s;
    o.style.width = clampedWpx + 'px';
    o.style.height = clampedHpx + 'px';
    const round = (v: number): number => Math.round(v * 100) / 100;
    const xIn = dragState.item.querySelector<HTMLInputElement>('[data-pdf-signable="x"]');
    const yIn = dragState.item.querySelector<HTMLInputElement>('[data-pdf-signable="y"]');
    const wIn = dragState.item.querySelector<HTMLInputElement>('[data-pdf-signable="width"]');
    const hIn = dragState.item.querySelector<HTMLInputElement>('[data-pdf-signable="height"]');
    if (xIn) xIn.value = String(round(ptToUnit(coord.xPt, unit)));
    if (yIn) yIn.value = String(round(ptToUnit(coord.yPt, unit)));
    if (wIn) wIn.value = String(round(wForm));
    if (hIn) hIn.value = String(round(hForm));
  }

  function onDragEnd(): void {
    if (!dragState) return;
    const state = dragState;
    dragState.overlay.classList.remove('dragging');
    setIsDragging(false);
    document.removeEventListener('mousemove', onDragMove);
    document.removeEventListener('mouseup', onDragEnd);

    if (preventBoxOverlap) {
      const boxes = getBoxesFromForm();
      const current = boxes[state.boxIndex];
      if (current) {
        const overlapsOther = boxes.some((b, i) => i !== state.boxIndex && boxesOverlap(current, b));
        if (overlapsOther) {
          const round = (v: number): number => Math.round(v * 100) / 100;
          const xIn = state.item.querySelector<HTMLInputElement>('[data-pdf-signable="x"]');
          const yIn = state.item.querySelector<HTMLInputElement>('[data-pdf-signable="y"]');
          const wIn = state.item.querySelector<HTMLInputElement>('[data-pdf-signable="width"]');
          const hIn = state.item.querySelector<HTMLInputElement>('[data-pdf-signable="height"]');
          if (xIn) xIn.value = String(round(state.startFormX));
          if (yIn) yIn.value = String(round(state.startFormY));
          if (wIn) wIn.value = String(round(state.startFormW));
          if (hIn) hIn.value = String(round(state.startFormH));
          alert(noOverlapMessage);
        }
      }
    }

    dragState = null;
    onOverlaysUpdate();
  }

  canvasWrapper.addEventListener('mousedown', onOverlayMouseDown);

  return {
    setSelectedBoxIndex,
    getSelectedBoxIndex: () => selectedBoxIndex,
  };
}
