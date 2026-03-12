<?php

/**
 * @apiDefine AuditLogResponse
 *
 * @apiSuccess {Object} data
 * @apiSuccess {String} data.type
 * @apiSuccess {String} data.id
 * @apiSuccess {String} data.module
 * @apiSuccess {String} data.action
 * @apiSuccess {String} data.status
 * @apiSuccess {String} data.reference_id
 * @apiSuccess {String} data.reference_name
 * @apiSuccess {Number} data.user_id
 * @apiSuccess {String} data.user_type
 * @apiSuccess {String} data.user_name
 * @apiSuccess {String} data.user_type_label
 * @apiSuccess {Number} data.actor_id
 * @apiSuccess {String} data.actor_type
 * @apiSuccess {String} data.actor_name
 * @apiSuccess {String} data.actor_type_label
 * @apiSuccess {String} data.ip_address
 * @apiSuccess {String} data.user_agent
 * @apiSuccess {Object} data.request
 * @apiSuccess {String} data.created_at
 * @apiSuccess {String} data.updated_at
 */
