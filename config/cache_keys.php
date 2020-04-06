<?php
/**
 * Created by PhpStorm.
 * User: teng
 * Date: 2020/4/5
 * Time: 2:39 AM
 */

return [
    // 定时任务
    'crontab_list'              => 'crontab_list',
    'crontab_cache_time'        => 7200,

    // 门店
    'store_info'                => 'store_info',
    'store_menu'                => 'store_menu',
    'store_menu_classes'        => 'store_menu_classes',
    'store_menu_dish'           => 'store_menu_dish',
    'store_menu_dish_code'      => 'store_menu_dish_code',
    'store_cache_time'          => 86400,

    // 菜品
    'dish_info'                 => 'dish_info',
    'dish_cache_time'           => 86400,

    // 套餐
    'combo_info'                => 'combo_info',
    'multi_combo_info'          => 'multi_combo_info',
    'combo_cache_time'          => 86400,

    // 台位
    'table_list'                => 'table_list',
    'table_info'                => 'table_info',
    'table_cache_time'          => 86400,

    // 辰森
    'choice_estimates'          => 'choice:estimates',
    'choice_table_state'        => 'choice:table_state',
    'choice_again'              => 'choice:again',
    'choice_cache_time'         => 7200,

    // 订单
    'order_notify'              => 'order_notify',
    'notify_cache_time'         => 7200,

    // 订单锁
    'order_lock'                => 'order_lock',
    'order_lock_time'           => 7200,

    // 台位用户
    'table_user'                => 'table_user',

    // 用户
    'user_info'                 => 'user_info',
    'user_favorite'             => 'user:favorite',
    'user_cno'                  => 'user:cno',
    'user_favorite_cache_time'  => 7200,
    'user_cache_time'           => 86400,
    'user_store_table_label'    => 'user_store_table_label',

    // 购物车
    'shopping_cart'             => 'shopping_cart',
    'table_shopping_cart'       => 'table_shopping_cart',
    'shopping_cart_people'      => 'shopping_cart_people',
    'table_shopping_cache_time' => 7200,

    // token
    'oauth_token'               => 'token',
    'token_cache_time'          => 86400,

    // 排行榜
    'ranking_store'             => 'ranking',
    'ranking_dish_sales'        => 'ranking_sales',
    'ranking_dish_score'        => 'ranking_dish_score',
    'score_dish_filter'         => 'score_dish_filter',

    // 外带
    'takeout_table'             => 'takeout_table',
    'takeout_cache_time'        => 86400,
    'takeout_contact_info'      => 'takeout_contact_info',


    //多人点餐提示文字
    'people_order_text'         => 'people_order_text',
    'people_order_cache_time'   => 3600,

    // 门店活动
    'store_food_activity'       => 'store_food_activity',

    // 订单菜品列表
    'order_food_list'           => 'order_food_list',

    // 订单号
    'order_no'                  => 'order_no',
    // 台位订单号
    'table_order_no'            => 'table_order_no',
    // 用户订单号
    'order_no_unionid'          => 'order_no_unionid',

    // 抽奖活动
    'activity_draw'             => 'activity_draw',

    //
    'con_table_user' => 'con:table:user',
];