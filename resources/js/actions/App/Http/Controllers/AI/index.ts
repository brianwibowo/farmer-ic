import AiConversationController from './AiConversationController'
import AiMessageController from './AiMessageController'
import AiVoiceController from './AiVoiceController'
import AiKnowledgeDocumentController from './AiKnowledgeDocumentController'

const AI = {
    AiConversationController: Object.assign(AiConversationController, AiConversationController),
    AiMessageController: Object.assign(AiMessageController, AiMessageController),
    AiVoiceController: Object.assign(AiVoiceController, AiVoiceController),
    AiKnowledgeDocumentController: Object.assign(AiKnowledgeDocumentController, AiKnowledgeDocumentController),
}

export default AI