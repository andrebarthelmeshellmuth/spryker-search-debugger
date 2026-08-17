import Component from 'ShopUi/models/component';

interface TreeEdge {
    from: string;
    to: string;
}

const SVG_NAMESPACE = 'http://www.w3.org/2000/svg';
const RESIZE_DEBOUNCE_MS = 150;

/**
 * The row's own `gap` CSS property is disabled in favor of this — every chip's horizontal spacing is set
 * explicitly via `margin-left` by {@see SearchDebugTreeDiagram.applyTreeLayout}, since that method needs
 * to add EXTRA space on top of the normal gap for some chips (to center a parent over its children) and
 * mixing an automatic flex `gap` with manual per-item overrides is more error-prone than owning the whole
 * spacing computation in one place.
 *
 * @var number
 */
const BASE_GAP_PX = 12;

/**
 * Draws the connector lines between one analyzer stage's token chips and the specific chips they produced
 * in the next stage (see search-debug-tree-diagram.twig — the rows themselves are plain server-rendered
 * HTML; only the connector geometry needs real pixel positions, which depend on the rendered token text's
 * width and are therefore unknowable at template time), AND repositions each row's chips so a parent sits
 * centered above the children it produced, rather than everything left-packed — see
 * {@see applyTreeLayout}'s own docblock.
 */
export default class SearchDebugTreeDiagram extends Component {
    labelsContainer: HTMLElement;
    rowsContainer: HTMLElement;
    scrollContainer: HTMLElement;
    edgesContainer: SVGSVGElement;
    edgesDataScript: HTMLScriptElement;
    edges: TreeEdge[];
    resizeTimeoutId: number;

    protected init(): void {
        this.labelsContainer = <HTMLElement>this.querySelector(`.${this.jsName}__labels`);
        this.rowsContainer = <HTMLElement>this.querySelector(`.${this.jsName}__rows`);
        this.scrollContainer = <HTMLElement>this.querySelector(`.${this.jsName}__scroll`);
        this.edgesContainer = <SVGSVGElement>this.querySelector(`.${this.jsName}__edges`);
        this.edgesDataScript = <HTMLScriptElement>this.querySelector(`.${this.jsName}__edges-data`);

        if (!this.labelsContainer || !this.rowsContainer || !this.scrollContainer || !this.edgesContainer || !this.edgesDataScript) {
            return;
        }

        this.edges = this.parseEdges();
        this.layoutAndDraw();
        window.addEventListener('resize', () => this.scheduleRedraw());
    }

    /**
     * A malformed payload degrades to "no connector lines, natural left-packed layout" (the rows are
     * still fully readable on their own), never a page-breaking error.
     */
    protected parseEdges(): TreeEdge[] {
        try {
            const parsed = JSON.parse(this.edgesDataScript.textContent || '[]');

            return Array.isArray(parsed) ? parsed : [];
        } catch {
            return [];
        }
    }

    protected scheduleRedraw(): void {
        window.clearTimeout(this.resizeTimeoutId);
        this.resizeTimeoutId = window.setTimeout(() => this.layoutAndDraw(), RESIZE_DEBOUNCE_MS);
    }

    protected layoutAndDraw(): void {
        this.syncLabelHeights();

        // Nothing to center/connect for a single straight chain (no branching at all) — the natural,
        // already-correct left-packed layout stays untouched in that case.
        if (this.edges.length === 0) {
            return;
        }

        this.applyTreeLayout();
        this.drawEdges();
    }

    /**
     * The label column and the row column (see search-debug-tree-diagram.scss's own `__labels`/`__scroll`
     * split) are two independently-flowing layout boxes now, with no native CSS way to keep a label's
     * height matched to its own row's — a long char-filter mapping list can make a label multi-line
     * regardless of whether that stage's own row branches at all, so this runs unconditionally, not only
     * when there are edges to draw. Matched purely by INDEX: a label and its row are rendered in the same
     * stage order (see the twig), one label per row, so position in each container's own children list is
     * enough on its own — no id needed. Resets both to `auto` first so a stale height from a PRIOR pass
     * (e.g. a wider window before a resize) never compounds into an ever-growing max.
     */
    protected syncLabelHeights(): void {
        const labels = Array.from(this.labelsContainer.children) as HTMLElement[];
        const rows = Array.from(this.rowsContainer.children) as HTMLElement[];

        labels.forEach((label: HTMLElement, index: number) => {
            const row = rows[index];

            if (!row) {
                return;
            }

            label.style.height = 'auto';
            row.style.height = 'auto';

            const height = Math.max(label.getBoundingClientRect().height, row.getBoundingClientRect().height);

            label.style.height = `${height}px`;
            row.style.height = `${height}px`;
        });
    }

    /**
     * A GENERAL layout rule, applied uniformly to every row, not a one-off fix for any specific word: a
     * parent chip is repositioned to sit centered above the AVERAGE horizontal position of the children it
     * produced, instead of every row being independently left-packed (the plain-CSS fallback, still what
     * renders without JS). Without this, a node whose own children end up far to the right in a later,
     * wider row (e.g. an edge-ngram fan-out) stays exactly where its narrow left-packed row put it,
     * forcing the connector line into a long diagonal sweep and leaving parent/child visually unrelated
     * at a glance — confirmed live on "Bandscheiben-Drehstuhl": "Drehstuhl"/"stuhl" stayed left-packed
     * immediately after "Bandscheiben" while their own edge-ngram children rendered far to the right.
     *
     * Processes rows bottom-up (the LAST/deepest row first) since a parent's desired position depends on
     * its children's FINAL positions, which must already be settled — the last row itself is untouched
     * (nothing to center it against), left-to-right in original token order exactly as the plain CSS
     * layout would already place it.
     *
     * Implemented as an explicit `margin-left` per chip (see {@see BASE_GAP_PX}) rather than
     * `position: absolute`: margin keeps every chip in normal flex flow, so the row's own natural content
     * width — and therefore the whole diagram's horizontal scroll extent {@see drawEdges} reads via
     * `wrapper.scrollWidth` — still grows correctly to fit whatever the layout ends up needing, with no
     * separate width bookkeeping required.
     *
     * A single left-to-right sweep per row both applies the centering AND guarantees no overlap: a
     * child's OWN sibling order is never changed (only Elasticsearch's real token order, already the DOM
     * order), so a parent is only ever pushed right of where it would naturally sit, never left of its
     * preceding sibling — the same reasoning a Reingold-Tilford-style tree layout relies on, simplified
     * here since this diagram never needs to re-thread contours across unrelated subtrees. A known,
     * accepted limitation: when two parents' own children CROSS (see the decompound/stem stage crossing
     * already visible in this diagram), a later parent whose children sit further left than an earlier
     * parent's still only ever gets pushed right by the overlap guard, not repositioned left to match its
     * own children more closely — the connector line stays correct either way (it's drawn from wherever
     * the chip actually ends up), only the visual straightness of that one edge is best-effort.
     */
    protected applyTreeLayout(): void {
        const rows = Array.from(this.querySelectorAll(`.${this.jsName}__rows > .search-debug-tree-diagram__row`));

        if (rows.length === 0) {
            return;
        }

        const childIdsByParentId = new Map<string, string[]>();
        this.edges.forEach((edge: TreeEdge) => {
            const children = childIdsByParentId.get(edge.from) ?? [];
            children.push(edge.to);
            childIdsByParentId.set(edge.from, children);
        });

        const centerXById = new Map<string, number>();

        for (let rowIndex = rows.length - 1; rowIndex >= 0; rowIndex--) {
            const chips = Array.from(
                rows[rowIndex].querySelectorAll<HTMLElement>('.search-debug-tree-diagram__node'),
            );

            let cursor = 0;

            chips.forEach((chip: HTMLElement, chipIndex: number) => {
                const id = chip.dataset.nodeId;
                // getBoundingClientRect(), not offsetWidth: this runs once per layout pass (init, and
                // every debounced resize), and a PRIOR pass's own margin-left never affects an element's
                // own reported width either way — re-measuring is simply the same source `drawEdges`
                // already trusts for real pixel geometry.
                const naturalWidth = chip.getBoundingClientRect().width;
                const childIds = id ? childIdsByParentId.get(id) ?? [] : [];
                const childCenters = childIds
                    .map((childId: string) => centerXById.get(childId))
                    .filter((value): value is number => value !== undefined);

                const desiredCenter = childCenters.length > 0
                    ? childCenters.reduce((sum: number, value: number) => sum + value, 0) / childCenters.length
                    : cursor + naturalWidth / 2;

                const minLeft = chipIndex === 0 ? 0 : cursor + BASE_GAP_PX;
                const left = Math.max(desiredCenter - naturalWidth / 2, minLeft);

                chip.style.marginLeft = `${left - cursor}px`;

                cursor = left + naturalWidth;

                if (id) {
                    centerXById.set(id, left + naturalWidth / 2);
                }
            });
        }
    }

    /**
     * Every coordinate is measured relative to `.search-debug-tree-diagram__scroll` (the SVG's own
     * positioned ancestor, see the scss) via `getBoundingClientRect()` — chip width is text-dependent and
     * genuinely unknowable any other way. Scroll-position-independent despite that: the SVG scrolls
     * together with the chips (both are descendants of the same `overflow-x: auto` element), so their
     * RELATIVE difference — the only thing this method ever reads — stays constant regardless of where
     * the region happens to be scrolled to at draw time. A curve (not a straight line) between each
     * parent/child pair, vertically through their shared row gap's midpoint, so a wide fan-out (several
     * children under one parent) reads as a visibly separated bundle rather than overlapping straight
     * lines converging on one point.
     */
    protected drawEdges(): void {
        const scrollRect = this.scrollContainer.getBoundingClientRect();

        this.edgesContainer.setAttribute('width', String(this.scrollContainer.scrollWidth));
        this.edgesContainer.setAttribute('height', String(this.scrollContainer.scrollHeight));
        this.edgesContainer.innerHTML = '';

        this.edges.forEach((edge: TreeEdge) => {
            const fromNode = <HTMLElement>this.querySelector(`[data-node-id="${edge.from}"]`);
            const toNode = <HTMLElement>this.querySelector(`[data-node-id="${edge.to}"]`);

            if (!fromNode || !toNode) {
                return;
            }

            const fromRect = fromNode.getBoundingClientRect();
            const toRect = toNode.getBoundingClientRect();

            const x1 = fromRect.left - scrollRect.left + fromRect.width / 2;
            const y1 = fromRect.bottom - scrollRect.top;
            const x2 = toRect.left - scrollRect.left + toRect.width / 2;
            const y2 = toRect.top - scrollRect.top;
            const midY = (y1 + y2) / 2;

            const path = document.createElementNS(SVG_NAMESPACE, 'path');
            path.setAttribute('class', 'search-debug-tree-diagram__edge-path');
            path.setAttribute('d', `M ${x1} ${y1} C ${x1} ${midY}, ${x2} ${midY}, ${x2} ${y2}`);
            this.edgesContainer.appendChild(path);
        });
    }
}
