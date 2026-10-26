<?php

return [
    // Model/table prefixes are explicit; no inheritance, wildcards, or bypass.
    'roles' => [
        'owner' => [
            'activity_photos.view' => 'any',

            'activity_types.view' => 'any',

            'ai_attachments.view' => 'any',

            'ai_conversations.view' => 'any',

            'ai_knowledge_chunks.view' => 'any',

            'ai_knowledge_documents.view' => 'any',

            'ai_messages.view' => 'any',

            'ai_usage_events.view' => 'any',

            'audit_logs.view' => 'any',

            'commodities.view' => 'any',

            'crop_varieties.view' => 'any',

            'farms.view' => 'any',

            'farm_activities.view' => 'any',

            'farm_risks.view' => 'any',

            'fertilization_details.view' => 'any',

            'financial_budgets.view' => 'any',

            'financial_categories.view' => 'any',

            'financial_projections.view' => 'any',

            'financial_transactions.view' => 'any',

            'harvest_details.view' => 'any',

            'incident_details.view' => 'any',

            'irrigation_details.view' => 'any',

            'memberships.view' => 'any',

            'notifications.view' => 'any',

            'operational_targets.view' => 'any',

            'pesticide_details.view' => 'any',

            'planting_cycles.view' => 'any',

            'planting_details.view' => 'any',

            'plant_observations.view' => 'any',

            'roles.view' => 'any',

            'teams.view' => 'any',

            'team_invitations.view' => 'any',

            'users.view' => 'any',

            'weather_records.view' => 'any',

            'ai_conversations.create' => 'any',
            'ai_conversations.update' => 'any',
            'ai_conversations.delete' => 'any',

            'ai_messages.create' => 'any',
            'ai_messages.update' => 'any',
            'ai_messages.delete' => 'any',

            'ai_attachments.create' => 'any',
            'ai_attachments.update' => 'any',
            'ai_attachments.delete' => 'any',

            'ai_knowledge_documents.create' => 'any',
            'ai_knowledge_documents.update' => 'any',
            'ai_knowledge_documents.delete' => 'any',

            'ai.access' => 'any',
        ],

        'superadmin' => [
            'farms.view' => 'any',
            'farms.create' => 'any',
            'farms.update' => 'any',
            'farms.delete' => 'any',

            'activity_types.view' => 'any',
            'activity_types.create' => 'any',
            'activity_types.update' => 'any',
            'activity_types.delete' => 'any',

            'commodities.view' => 'any',
            'commodities.create' => 'any',
            'commodities.update' => 'any',
            'commodities.delete' => 'any',

            'crop_varieties.view' => 'any',
            'crop_varieties.create' => 'any',
            'crop_varieties.update' => 'any',
            'crop_varieties.delete' => 'any',

            'financial_categories.view' => 'any',
            'financial_categories.create' => 'any',
            'financial_categories.update' => 'any',
            'financial_categories.delete' => 'any',

            'users.view' => 'any',
            'users.create' => 'any',
            'users.update' => 'any',
            'users.delete' => 'any',

            'teams.view' => 'any',
            'teams.create' => 'any',
            'teams.update' => 'any',
            'teams.delete' => 'any',

            'memberships.view' => 'any',
            'memberships.create' => 'any',
            'memberships.update' => 'any',
            'memberships.delete' => 'any',

            'team_invitations.view' => 'any',
            'team_invitations.create' => 'any',
            'team_invitations.update' => 'any',
            'team_invitations.delete' => 'any',

            'roles.view' => 'any',

            'audit_logs.view' => 'any',

            'notifications.view' => 'any',
        ],

        'admin' => [
            'farms.view' => 'own',

            'activity_types.view' => 'any',

            'commodities.view' => 'any',

            'crop_varieties.view' => 'any',

            'financial_categories.view' => 'any',

            'teams.view' => 'any',

            'notifications.view' => 'own',
            'notifications.update' => 'own',

            'teams.leave' => 'any',

            'financial_transactions.view' => 'own',
            'financial_transactions.create' => 'own',

            'financial_projections.view' => 'own',
            'financial_projections.create' => 'own',

            'financial_budgets.view' => 'own',
            'financial_budgets.create' => 'own',
        ],

        'petugas' => [
            'farms.view' => 'own',

            'activity_types.view' => 'any',

            'commodities.view' => 'any',

            'crop_varieties.view' => 'any',

            'financial_categories.view' => 'any',

            'teams.view' => 'any',

            'notifications.view' => 'own',
            'notifications.update' => 'own',

            'teams.leave' => 'any',

            'farm_activities.view' => 'own',
            'farm_activities.create' => 'own',

            'activity_photos.view' => 'own',
            'activity_photos.create' => 'own',

            'planting_cycles.view' => 'own',
            'planting_cycles.create' => 'own',

            'planting_details.view' => 'own',
            'planting_details.create' => 'own',

            'fertilization_details.view' => 'own',
            'fertilization_details.create' => 'own',

            'irrigation_details.view' => 'own',
            'irrigation_details.create' => 'own',

            'pesticide_details.view' => 'own',
            'pesticide_details.create' => 'own',

            'incident_details.view' => 'own',
            'incident_details.create' => 'own',

            'harvest_details.view' => 'own',
            'harvest_details.create' => 'own',

            'plant_observations.view' => 'own',
            'plant_observations.create' => 'own',

            'weather_records.view' => 'own',
            'weather_records.create' => 'own',

            'farm_risks.view' => 'own',
            'farm_risks.create' => 'own',

            'operational_targets.view' => 'own',
            'operational_targets.create' => 'own',

            'planting_cycles.update' => 'own',

            'plant_observations.update' => 'own',

            'planting_details.update' => 'own',
        ],

    ],

    'farm_assignment_limits' => [
        'petugas' => 1,
    ],
];
