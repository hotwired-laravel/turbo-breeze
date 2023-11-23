import Stimulus from '../libs/controllers'

import ResponsiveNav from './responsive_nav_controller'
Stimulus.register('responsive-nav', ResponsiveNav)

import Flash from './flash_controller'
Stimulus.register('flash', Flash)

import Dropdown from './dropdown_controller'
Stimulus.register('dropdown', Dropdown)

import Modal from './modal_controller'
Stimulus.register('modal', Modal)

import ModalTrigger from './modal_trigger_controller'
Stimulus.register('modal-trigger', ModalTrigger)

export default Stimulus
