import { onBeforeUnmount, ref, type Ref } from 'vue';

type Gesture = {
    pointerId: number;
    startX: number;
    startY: number;
    lastX: number;
    scrollLeft: number;
    slideIndex: number;
    startedAt: number;
};

export function closestCarouselSlide(element: HTMLElement): number {
    const slides = Array.from(element.children) as HTMLElement[];
    let closest = 0;
    let distance = Infinity;
    slides.forEach((slide, index) => {
        const offset = Math.abs(slide.offsetLeft - element.scrollLeft);
        if (offset < distance) {
            distance = offset;
            closest = index;
        }
    });
    return closest;
}

export function useCarouselDrag(
    track: Ref<HTMLElement | undefined>,
    navigate: (index: number) => void,
) {
    const isDragging = ref(false);
    let gesture: Gesture | undefined;
    let suppressClick = false;

    function reset(): void {
        const pointerId = gesture?.pointerId;
        gesture = undefined;
        isDragging.value = false;
        track.value?.classList.remove('is-dragging');
        if (
            pointerId !== undefined &&
            track.value?.hasPointerCapture(pointerId)
        ) {
            track.value.releasePointerCapture(pointerId);
        }
    }

    function pointerDown(event: PointerEvent): void {
        const element = track.value;
        if (
            !element ||
            !event.isPrimary ||
            event.button !== 0 ||
            element.children.length < 2
        )
            return;
        reset();
        suppressClick = false;
        // Stop an ongoing arrow-button animation at the current visible position.
        element.scrollTo({ left: element.scrollLeft, behavior: 'instant' });
        gesture = {
            pointerId: event.pointerId,
            startX: event.clientX,
            startY: event.clientY,
            lastX: event.clientX,
            scrollLeft: element.scrollLeft,
            slideIndex: closestCarouselSlide(element),
            startedAt: performance.now(),
        };
    }

    function pointerMove(event: PointerEvent): void {
        const element = track.value;
        if (!element || !gesture || event.pointerId !== gesture.pointerId)
            return;
        const deltaX = event.clientX - gesture.startX;
        const deltaY = event.clientY - gesture.startY;
        if (!isDragging.value) {
            if (Math.max(Math.abs(deltaX), Math.abs(deltaY)) < 6) return;
            if (Math.abs(deltaY) > Math.abs(deltaX)) {
                reset();
                return;
            }
            isDragging.value = true;
            // Disable snapping immediately so it cannot resist pointer movement.
            element.classList.add('is-dragging');
            element.setPointerCapture(event.pointerId);
        }
        gesture.lastX = event.clientX;
        event.preventDefault();
        element.scrollLeft = gesture.scrollLeft - deltaX;
    }

    function pointerUp(event: PointerEvent): void {
        const element = track.value;
        if (!element || !gesture || event.pointerId !== gesture.pointerId)
            return;
        const current = gesture;
        const wasDragging = isDragging.value;
        const deltaX = current.lastX - current.startX;
        let index = closestCarouselSlide(element);
        const distance = Math.abs(deltaX);
        const velocity =
            distance / Math.max(1, performance.now() - current.startedAt);
        if (
            index === current.slideIndex &&
            (distance >= Math.min(48, element.clientWidth * 0.15) ||
                (distance > 16 && velocity > 0.5))
        ) {
            index += deltaX < 0 ? 1 : -1;
        }
        suppressClick = wasDragging;
        reset();
        if (wasDragging) navigate(index);
    }

    function pointerCancel(event: PointerEvent): void {
        if (!gesture || event.pointerId !== gesture.pointerId) return;
        const element = track.value;
        const wasDragging = isDragging.value;
        const index = element ? closestCarouselSlide(element) : 0;
        reset();
        if (wasDragging) navigate(index);
    }

    function click(event: MouseEvent): void {
        if (!suppressClick) return;
        suppressClick = false;
        event.preventDefault();
        event.stopPropagation();
    }

    onBeforeUnmount(reset);
    return {
        isDragging,
        pointerDown,
        pointerMove,
        pointerUp,
        pointerCancel,
        click,
    };
}
