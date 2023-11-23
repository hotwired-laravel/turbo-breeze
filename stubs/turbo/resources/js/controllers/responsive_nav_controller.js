import { Controller } from '@hotwired/stimulus'

// Usage: data-controller="responsive-nav"
export default class extends Controller {
    static values = {
        open: false,
    }

    connect() {
        if (! this.openValue) {
            this.close()
        }
    }

    open() {
        this.openValue = true
    }

    close() {
        this.openValue = false
    }

    toggle() {
        this.openValue = ! this.openValue
    }

    // private

    openValueChanged() {
        if (this.openValue) {
            this.element.setAttribute('open', true)
        } else {
            this.element.removeAttribute('open')
        }
    }
}
