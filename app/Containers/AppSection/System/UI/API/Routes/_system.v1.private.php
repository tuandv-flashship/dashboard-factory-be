<?php

/**
 * @apiDefine DevArtisanResponse
 *
 * @apiSuccess {Object} data
 * @apiSuccess {String} data.command
 * @apiSuccess {Object} data.options
 * @apiSuccess {Number} data.exit_code
 * @apiSuccess {String} data.output
 * @apiSuccess {Boolean} data.success
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
