import DashboardController from './DashboardController'
import AI from './AI'
import Teams from './Teams'
import Settings from './Settings'

const Controllers = {
    DashboardController: Object.assign(DashboardController, DashboardController),
    AI: Object.assign(AI, AI),
    Teams: Object.assign(Teams, Teams),
    Settings: Object.assign(Settings, Settings),
}

export default Controllers