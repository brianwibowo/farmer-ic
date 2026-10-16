import TeamInvitationController from './TeamInvitationController'
import TeamController from './TeamController'
import TeamMemberController from './TeamMemberController'
const Teams = {
    TeamInvitationController: Object.assign(TeamInvitationController, TeamInvitationController),
TeamController: Object.assign(TeamController, TeamController),
TeamMemberController: Object.assign(TeamMemberController, TeamMemberController),
}

export default Teams