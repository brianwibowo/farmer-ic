import DashboardController from './DashboardController'
import Teams from './Teams'
import Settings from './Settings'

const Controllers = {
    DashboardController: Object.assign(DashboardController, DashboardController),
    Teams: Object.assign(Teams, Teams),
    Settings: Object.assign(Settings, Settings),
}

export default Controllers