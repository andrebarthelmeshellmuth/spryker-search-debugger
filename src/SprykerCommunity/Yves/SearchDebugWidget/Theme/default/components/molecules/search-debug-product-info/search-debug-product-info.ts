import Component from 'ShopUi/models/component';

export default class SearchDebugProductInfo extends Component {
    /**
     * The button toggling whether the overlay stays open regardless of hover.
     */
    pinToggle: HTMLButtonElement;

    /**
     * The panel `.search-debug-pin-toggle` sits inside — kept visible via the `--pinned` modifier
     * class while pinned (see the scss), independently of `:hover`.
     */
    overlay: HTMLElement;

    protected readyCallback(): void {
        this.pinToggle = <HTMLButtonElement>this.querySelector(`.${this.jsName}__pin-toggle`);
        this.overlay = <HTMLElement>this.querySelector(`.${this.jsName}__overlay`);

        if (!this.pinToggle || !this.overlay) {
            return;
        }

        this.mapEvents();
    }

    protected mapEvents(): void {
        this.pinToggle.addEventListener('click', () => this.togglePinned());
    }

    /**
     * Flips both the button's own pressed state (also its visual "active" styling, see the scss) and the
     * overlay's `--pinned` modifier class in one place, so the two can never fall out of sync with
     * each other.
     */
    protected togglePinned(): void {
        const isPinned = this.pinToggle.getAttribute('aria-pressed') === 'true';

        this.pinToggle.setAttribute('aria-pressed', isPinned ? 'false' : 'true');
        this.overlay.classList.toggle('search-debug-overlay--pinned', !isPinned);
    }
}
