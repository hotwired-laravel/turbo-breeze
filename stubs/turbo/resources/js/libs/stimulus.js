import { Application } from '@hotwired/stimulus'

const Stimulus = Application.start()

// Set to true in development...
Stimulus.debug = false
window.Stimulus = Stimulus

export default { Stimulus }
