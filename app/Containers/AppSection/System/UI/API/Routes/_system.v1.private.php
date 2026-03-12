<?php

/**
 * @apiDefine SystemCommandResultResponse
 *
 * @apiSuccess {Object} data
 * @apiSuccess {String} data.type
 * @apiSuccess {String} data.id
 * @apiSuccess {String} data.job_id
 * @apiSuccess {String} data.action
 * @apiSuccess {String} data.command
 * @apiSuccess {String} data.status
 * @apiSuccess {Number} data.exit_code
 * @apiSuccess {String} data.output
 * @apiSuccess {String} data.error
 * @apiSuccess {String} data.started_at
 * @apiSuccess {String} data.finished_at
 * @apiSuccess {String} data.created_at
 */


/**
 * @apiDefine SystemCacheStatusResponse
 *
 * @apiSuccess {Object} data
 * @apiSuccess {String} data.type
 * @apiSuccess {String} data.id
 * @apiSuccess {Number} data.cache_size_bytes
 * @apiSuccess {String} data.cache_size
 * @apiSuccess {Object[]} data.types
 */

/**
 * @apiDefine SystemCacheActionResponse
 *
 * @apiSuccess {Object} data
 * @apiSuccess {String} data.type
 * @apiSuccess {String} data.id
 * @apiSuccess {String} data.action
 * @apiSuccess {Boolean} data.success
 * @apiSuccess {String} data.message
 * @apiSuccess {String[]} data.details
 */
