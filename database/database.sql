-- =====================================================================
-- Pet House - Core PHP + MySQL Database Schema
-- Complete standalone SQL schema with pre-seeded users and demo data.
-- Works directly in phpMyAdmin, InfinityFree, MySQL 8+, and MariaDB 10.4+.
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- =====================================================================
-- 1. TABLE CREATIONS
-- =====================================================================

-- USERS  (roles: ADMIN, CUSTOMER, GROOMER, DELIVERY_PARTNER)
CREATE TABLE IF NOT EXISTS `users` (
  `id`              VARCHAR(40)  NOT NULL,
  `email`           VARCHAR(191) NOT NULL,
  `phone`           VARCHAR(20)  NOT NULL,
  `name`            VARCHAR(150) NOT NULL,
  `password_hash`   VARCHAR(255) NOT NULL,
  `role`            VARCHAR(20)  NOT NULL DEFAULT 'CUSTOMER',
  `address`         TEXT         NULL,
  `avatar`          VARCHAR(255) NULL,
  `reward_points`   INT          NOT NULL DEFAULT 0,
  `referral_code`   VARCHAR(30)  NULL,
  `referred_by`     VARCHAR(40)  NULL,
  `membership_tier` VARCHAR(20)  NOT NULL DEFAULT 'bronze',
  `active`          TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  UNIQUE KEY `uq_users_phone` (`phone`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_referral_code` (`referral_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PETS
CREATE TABLE IF NOT EXISTS `pets` (
  `id`         VARCHAR(40)  NOT NULL,
  `user_id`    VARCHAR(40)  NOT NULL,
  `name`       VARCHAR(100) NOT NULL,
  `species`    VARCHAR(40)  NOT NULL,
  `breed`      VARCHAR(100) NOT NULL,
  `age`        INT          NULL,
  `weight`     DECIMAL(6,2) NULL,
  `gender`     VARCHAR(10)  NULL,
  `avatar`     VARCHAR(255) NULL,
  `notes`      TEXT         NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pets_user` (`user_id`),
  CONSTRAINT `fk_pets_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- GROOMING SERVICES
CREATE TABLE IF NOT EXISTS `grooming_services` (
  `id`          VARCHAR(40)   NOT NULL,
  `name`        VARCHAR(150)  NOT NULL,
  `description` TEXT          NOT NULL,
  `price`       DECIMAL(10,2) NOT NULL,
  `duration`    INT           NOT NULL,
  `category`    VARCHAR(40)   NOT NULL,
  `image`       VARCHAR(255)  NULL,
  `active`      TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_gservices_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- GROOMING BOOKINGS
CREATE TABLE IF NOT EXISTS `grooming_bookings` (
  `id`         VARCHAR(40)  NOT NULL,
  `user_id`    VARCHAR(40)  NOT NULL,
  `pet_id`     VARCHAR(40)  NOT NULL,
  `service_id` VARCHAR(40)  NOT NULL,
  `date`       VARCHAR(20)  NOT NULL,
  `time`       VARCHAR(10)  NOT NULL,
  `status`     VARCHAR(20)  NOT NULL DEFAULT 'pending',
  `notes`      TEXT         NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_gbook_user` (`user_id`),
  KEY `idx_gbook_service` (`service_id`),
  KEY `idx_gbook_date` (`date`,`time`),
  CONSTRAINT `fk_gbook_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`),
  CONSTRAINT `fk_gbook_pet`     FOREIGN KEY (`pet_id`)     REFERENCES `pets`(`id`),
  CONSTRAINT `fk_gbook_service` FOREIGN KEY (`service_id`) REFERENCES `grooming_services`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- BOARDING ROOMS
CREATE TABLE IF NOT EXISTS `boarding_rooms` (
  `id`         VARCHAR(40)   NOT NULL,
  `name`       VARCHAR(100)  NOT NULL,
  `type`       VARCHAR(30)   NOT NULL,
  `price`      DECIMAL(10,2) NOT NULL,
  `capacity`   INT           NOT NULL,
  `amenities`  TEXT          NOT NULL,
  `image`      VARCHAR(255)  NULL,
  `active`     TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- BOARDING RESERVATIONS
CREATE TABLE IF NOT EXISTS `boarding_reservations` (
  `id`         VARCHAR(40)  NOT NULL,
  `user_id`    VARCHAR(40)  NOT NULL,
  `pet_id`     VARCHAR(40)  NOT NULL,
  `room_id`    VARCHAR(40)  NOT NULL,
  `check_in`   VARCHAR(20)  NOT NULL,
  `check_out`  VARCHAR(20)  NOT NULL,
  `status`     VARCHAR(20)  NOT NULL DEFAULT 'pending',
  `notes`      TEXT         NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bres_user` (`user_id`),
  KEY `idx_bres_room` (`room_id`),
  CONSTRAINT `fk_bres_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
  CONSTRAINT `fk_bres_pet`  FOREIGN KEY (`pet_id`)  REFERENCES `pets`(`id`),
  CONSTRAINT `fk_bres_room` FOREIGN KEY (`room_id`) REFERENCES `boarding_rooms`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- WALKING BOOKINGS
CREATE TABLE IF NOT EXISTS `walking_bookings` (
  `id`         VARCHAR(40)  NOT NULL,
  `user_id`    VARCHAR(40)  NOT NULL,
  `pet_id`     VARCHAR(40)  NOT NULL,
  `date`       VARCHAR(20)  NOT NULL,
  `duration`   INT          NOT NULL,
  `time`       VARCHAR(10)  NOT NULL,
  `status`     VARCHAR(20)  NOT NULL DEFAULT 'pending',
  `notes`      TEXT         NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wbook_user` (`user_id`),
  CONSTRAINT `fk_wbook_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
  CONSTRAINT `fk_wbook_pet`  FOREIGN KEY (`pet_id`)  REFERENCES `pets`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PRODUCTS
CREATE TABLE IF NOT EXISTS `products` (
  `id`             VARCHAR(40)   NOT NULL,
  `name`           VARCHAR(150)  NOT NULL,
  `description`    TEXT          NOT NULL,
  `price`          DECIMAL(10,2) NOT NULL,
  `original_price` DECIMAL(10,2) NULL,
  `category`       VARCHAR(60)   NOT NULL,
  `image`          VARCHAR(255)  NULL,
  `rating`         DECIMAL(3,1)  NOT NULL DEFAULT 0.0,
  `review_count`   INT           NOT NULL DEFAULT 0,
  `in_stock`       TINYINT(1)    NOT NULL DEFAULT 1,
  `stock_qty`      INT           NOT NULL DEFAULT 0,
  `featured`       TINYINT(1)    NOT NULL DEFAULT 0,
  `tags`           TEXT          NULL,
  `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_products_category` (`category`),
  KEY `idx_products_featured` (`featured`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ORDERS
CREATE TABLE IF NOT EXISTS `orders` (
  `id`             VARCHAR(40)   NOT NULL,
  `user_id`        VARCHAR(40)   NOT NULL,
  `total`          DECIMAL(10,2) NOT NULL,
  `subtotal`       DECIMAL(10,2) NOT NULL,
  `tax`            DECIMAL(10,2) NOT NULL DEFAULT 0,
  `discount`       DECIMAL(10,2) NOT NULL DEFAULT 0,
  `status`         VARCHAR(20)   NOT NULL DEFAULT 'pending',
  `payment_method` VARCHAR(20)   NOT NULL DEFAULT 'cod',
  `address`        TEXT          NULL,
  `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_orders_user` (`user_id`),
  KEY `idx_orders_status` (`status`),
  CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ORDER ITEMS
CREATE TABLE IF NOT EXISTS `order_items` (
  `id`         VARCHAR(40)   NOT NULL,
  `order_id`   VARCHAR(40)   NOT NULL,
  `product_id` VARCHAR(40)   NOT NULL,
  `quantity`   INT           NOT NULL,
  `price`      DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_oitems_order` (`order_id`),
  CONSTRAINT `fk_oitems_order`   FOREIGN KEY (`order_id`)   REFERENCES `orders`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_oitems_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CART ITEMS
CREATE TABLE IF NOT EXISTS `cart_items` (
  `id`         VARCHAR(40) NOT NULL,
  `user_id`    VARCHAR(40) NOT NULL,
  `product_id` VARCHAR(40) NOT NULL,
  `quantity`   INT          NOT NULL DEFAULT 1,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cart_user_product` (`user_id`,`product_id`),
  CONSTRAINT `fk_cart_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cart_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- WISHLIST ITEMS
CREATE TABLE IF NOT EXISTS `wishlist_items` (
  `id`         VARCHAR(40) NOT NULL,
  `user_id`    VARCHAR(40) NOT NULL,
  `product_id` VARCHAR(40) NOT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wish_user_product` (`user_id`,`product_id`),
  CONSTRAINT `fk_wish_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wish_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DELIVERIES
CREATE TABLE IF NOT EXISTS `deliveries` (
  `id`           VARCHAR(40)  NOT NULL,
  `order_id`     VARCHAR(40)  NOT NULL,
  `partner_id`   VARCHAR(40)  NOT NULL,
  `status`       VARCHAR(20)  NOT NULL DEFAULT 'assigned',
  `estimated_at` VARCHAR(30)  NULL,
  `completed_at` VARCHAR(30)  NULL,
  `proof_image`  VARCHAR(255) NULL,
  `notes`        TEXT         NULL,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_delivery_order` (`order_id`),
  KEY `idx_delivery_partner` (`partner_id`),
  KEY `idx_delivery_status` (`status`),
  CONSTRAINT `fk_delivery_order`   FOREIGN KEY (`order_id`)   REFERENCES `orders`(`id`),
  CONSTRAINT `fk_delivery_partner` FOREIGN KEY (`partner_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- NOTIFICATIONS
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`         VARCHAR(40)  NOT NULL,
  `user_id`    VARCHAR(40)  NOT NULL,
  `title`      VARCHAR(200) NOT NULL,
  `message`    TEXT         NOT NULL,
  `type`       VARCHAR(30)  NOT NULL DEFAULT 'general',
  `is_read`    TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_user` (`user_id`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SUPPORT TICKETS
CREATE TABLE IF NOT EXISTS `support_tickets` (
  `id`         VARCHAR(40)  NOT NULL,
  `user_id`    VARCHAR(40)  NOT NULL,
  `subject`    VARCHAR(200) NOT NULL,
  `message`    TEXT         NOT NULL,
  `status`     VARCHAR(20)  NOT NULL DEFAULT 'open',
  `priority`   VARCHAR(20)  NOT NULL DEFAULT 'medium',
  `response`   TEXT         NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ticket_user` (`user_id`),
  CONSTRAINT `fk_ticket_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- FAQS
CREATE TABLE IF NOT EXISTS `faqs` (
  `id`         VARCHAR(40)  NOT NULL,
  `question`   VARCHAR(300) NOT NULL,
  `answer`     TEXT         NOT NULL,
  `category`   VARCHAR(60)  NOT NULL,
  `sort_order` INT          NOT NULL DEFAULT 0,
  `active`     TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ADDRESSES
CREATE TABLE IF NOT EXISTS `addresses` (
  `id`         VARCHAR(40)  NOT NULL,
  `user_id`    VARCHAR(40)  NOT NULL,
  `label`      VARCHAR(60)  NOT NULL,
  `address`    TEXT         NOT NULL,
  `city`       VARCHAR(80)  NOT NULL,
  `pincode`    VARCHAR(10)  NOT NULL,
  `is_default` TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_addr_user` (`user_id`),
  CONSTRAINT `fk_addr_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- REWARD TRANSACTIONS
CREATE TABLE IF NOT EXISTS `reward_transactions` (
  `id`          VARCHAR(40)  NOT NULL,
  `user_id`     VARCHAR(40)  NOT NULL,
  `points`      INT          NOT NULL,
  `type`        VARCHAR(20)  NOT NULL,
  `source`      VARCHAR(40)  NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reward_user` (`user_id`),
  CONSTRAINT `fk_reward_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- COUPONS
CREATE TABLE IF NOT EXISTS `coupons` (
  `id`           VARCHAR(40)   NOT NULL,
  `code`         VARCHAR(40)   NOT NULL,
  `discount`     DECIMAL(10,2) NOT NULL,
  `type`         VARCHAR(20)   NOT NULL,
  `min_order`    DECIMAL(10,2) NOT NULL DEFAULT 0,
  `max_discount` DECIMAL(10,2) NULL,
  `expires_at`   DATETIME      NULL,
  `active`       TINYINT(1)    NOT NULL DEFAULT 1,
  `usage_limit`  INT           NULL,
  `usage_count`  INT           NOT NULL DEFAULT 0,
  `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_coupon_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- BLOG POSTS
CREATE TABLE IF NOT EXISTS `blog_posts` (
  `id`         VARCHAR(40)  NOT NULL,
  `author_id`  VARCHAR(40)  NOT NULL,
  `title`      VARCHAR(200) NOT NULL,
  `slug`       VARCHAR(220) NOT NULL,
  `excerpt`    VARCHAR(400) NOT NULL,
  `content`    LONGTEXT     NOT NULL,
  `category`   VARCHAR(60)  NOT NULL,
  `image`      VARCHAR(255) NULL,
  `published`  TINYINT(1)   NOT NULL DEFAULT 0,
  `read_time`  INT          NOT NULL DEFAULT 3,
  `tags`       TEXT         NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_blog_slug` (`slug`),
  KEY `idx_blog_published` (`published`),
  CONSTRAINT `fk_blog_author` FOREIGN KEY (`author_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- REVIEWS
CREATE TABLE IF NOT EXISTS `reviews` (
  `id`         VARCHAR(40)  NOT NULL,
  `user_id`    VARCHAR(40)  NOT NULL,
  `product_id` VARCHAR(40)  NOT NULL,
  `rating`     INT          NOT NULL,
  `comment`    TEXT         NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_review_product` (`product_id`),
  CONSTRAINT `fk_review_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`),
  CONSTRAINT `fk_review_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ADOPTION LISTINGS
CREATE TABLE IF NOT EXISTS `adoption_listings` (
  `id`           VARCHAR(40)  NOT NULL,
  `name`         VARCHAR(100) NOT NULL,
  `species`      VARCHAR(40)  NOT NULL,
  `breed`        VARCHAR(100) NOT NULL,
  `age`          VARCHAR(40)  NOT NULL,
  `description`  TEXT         NOT NULL,
  `image`        VARCHAR(255) NULL,
  `vaccinated`   TINYINT(1)   NOT NULL DEFAULT 0,
  `neutered`     TINYINT(1)   NOT NULL DEFAULT 0,
  `status`       VARCHAR(20)  NOT NULL DEFAULT 'available',
  `contact_info` VARCHAR(200) NULL,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_adopt_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- STORE SETTINGS
CREATE TABLE IF NOT EXISTS `store_settings` (
  `id`    VARCHAR(40)  NOT NULL,
  `key`   VARCHAR(80)  NOT NULL,
  `value` TEXT         NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_setting_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SESSIONS
CREATE TABLE IF NOT EXISTS `sessions` (
  `id`         VARCHAR(40)  NOT NULL,
  `user_id`    VARCHAR(40)  NOT NULL,
  `token`      VARCHAR(64)  NOT NULL,
  `expires_at` DATETIME     NOT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_session_token` (`token`),
  KEY `idx_session_user` (`user_id`),
  CONSTRAINT `fk_session_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- REFERRALS
CREATE TABLE IF NOT EXISTS `referrals` (
  `id`            VARCHAR(40) NOT NULL,
  `referrer_id`   VARCHAR(40) NOT NULL,
  `referee_id`    VARCHAR(40) NOT NULL,
  `code`          VARCHAR(30) NOT NULL,
  `status`        VARCHAR(20) NOT NULL DEFAULT 'pending',
  `reward_points` INT         NOT NULL DEFAULT 0,
  `completed_at`  DATETIME    NULL,
  `created_at`    DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_referral_referee` (`referee_id`),
  KEY `idx_referral_referrer` (`referrer_id`),
  CONSTRAINT `fk_ref_referrer` FOREIGN KEY (`referrer_id`) REFERENCES `users`(`id`),
  CONSTRAINT `fk_ref_referee`  FOREIGN KEY (`referee_id`)  REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- FILE UPLOADS
CREATE TABLE IF NOT EXISTS `file_uploads` (
  `id`         VARCHAR(40)  NOT NULL,
  `user_id`    VARCHAR(40)  NOT NULL,
  `file_name`  VARCHAR(255) NOT NULL,
  `file_type`  VARCHAR(20)  NOT NULL,
  `file_size`  INT          NOT NULL,
  `mime_type`  VARCHAR(100) NOT NULL,
  `url`        VARCHAR(255) NOT NULL,
  `category`   VARCHAR(40)  NOT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_file_user` (`user_id`),
  KEY `idx_file_category` (`category`),
  CONSTRAINT `fk_file_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 2. SEED DATA (STRICT DEPENDENCY ORDER FOR FOREIGN KEYS)
-- =====================================================================

-- 2.1 PARENT TABLE 1: USERS (bcrypt hash for password123)
INSERT INTO `users` (`id`, `email`, `phone`, `name`, `password_hash`, `role`, `address`, `reward_points`, `referral_code`, `membership_tier`, `active`) VALUES
('u_admin',    'admin@pethouse.com',    '9999999999', 'Admin User',     '$2y$10$tDZWut78i4pjMCUMiv342.hKMpd1ly/uXcifwvj2FxfJhokz2HdlG', 'ADMIN',            'Pet House HQ, Chhatrapati Sambhajinagar', 0,   'ADMINREF', 'gold',   1),
('u_customer', 'rahul@example.com',     '9876543210', 'Rahul Sharma',   '$2y$10$tDZWut78i4pjMCUMiv342.hKMpd1ly/uXcifwvj2FxfJhokz2HdlG', 'CUSTOMER',         '12 Ganesh Nagar, Chhatrapati Sambhajinagar', 250, 'RAHUL25',  'silver', 1),
('u_groomer',  'groomer@pethouse.com',  '8888888888', 'Priya Groomer',  '$2y$10$tDZWut78i4pjMCUMiv342.hKMpd1ly/uXcifwvj2FxfJhokz2HdlG', 'GROOMER',          NULL, 0, 'GROOMER1', 'bronze', 1),
('u_delivery', 'delivery@pethouse.com', '7777777777', 'Delivery Amit',  '$2y$10$tDZWut78i4pjMCUMiv342.hKMpd1ly/uXcifwvj2FxfJhokz2HdlG', 'DELIVERY_PARTNER', NULL, 0, 'DELIVER1', 'bronze', 1),
('u_cust2',    'neha@example.com',      '9777777777', 'Neha Verma',     '$2y$10$tDZWut78i4pjMCUMiv342.hKMpd1ly/uXcifwvj2FxfJhokz2HdlG', 'CUSTOMER',         '5 CIDCO, Chhatrapati Sambhajinagar', 120, 'NEHA12',   'bronze', 1)
ON DUPLICATE KEY UPDATE `password_hash` = VALUES(`password_hash`), `role` = VALUES(`role`), `active` = 1;

-- 2.2 PARENT TABLE 2: GROOMING SERVICES
INSERT INTO `grooming_services` (`id`,`name`,`description`,`price`,`duration`,`category`,`active`) VALUES
('gs_1','Basic Bath & Brush','A refreshing bath with premium shampoo, blow dry and brush out. Perfect for regular maintenance.',499.00,45,'basic',1),
('gs_2','Premium Grooming','Complete grooming package including bath, haircut, nail trim, ear cleaning and perfume.',999.00,90,'premium',1),
('gs_3','Spa & Relaxation','Relaxing spa treatment with massage, aromatherapy bath and coat conditioning.',1499.00,120,'spa',1),
('gs_4','Puppy First Groom','Gentle introduction grooming for puppies under 6 months. Stress-free and fun!',399.00,30,'specialty',1),
('gs_5','De-shedding Treatment','Reduces shedding by up to 90% with special tools and conditioning treatment.',799.00,60,'premium',1),
('gs_6','Feline Spa Package','Cat-friendly grooming with gentle handling, bath and brush.',699.00,60,'spa',1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `price` = VALUES(`price`);

-- 2.3 PARENT TABLE 3: BOARDING ROOMS
INSERT INTO `boarding_rooms` (`id`,`name`,`type`,`price`,`capacity`,`amenities`,`active`) VALUES
('br_1','Cozy Standard','standard',499.00,5,'["AC","CCTV","Daily Meals"]',1),
('br_2','Luxury Suite','luxury',1299.00,2,'["AC","CCTV","Play Area","Daily Meals","Webcam"]',1),
('br_3','Sharing Room','sharing',349.00,8,'["AC","CCTV","Daily Meals","Group Play"]',1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `price` = VALUES(`price`);

-- 2.4 PARENT TABLE 4: PRODUCTS
INSERT INTO `products` (`id`,`name`,`description`,`price`,`original_price`,`category`,`image`,`rating`,`review_count`,`in_stock`,`stock_qty`,`featured`,`tags`) VALUES
-- CAT FOOD (5 Products)
('pr_cf_1','Royal Canin Fit 32 Adult Cat Food (1.2kg)','Balanced and complete feed for adult cats over 1 year old. Supports ideal weight and hairball reduction.',899.00,1099.00,'food','cat_food_royal_canin.png',4.8,142,1,45,1,'["cat","food","dry"]'),
('pr_cf_2','Whiskas Ocean Fish Dry Cat Food (3kg)','Delicious ocean fish flavor dry cat food packed with essential nutrients, omega-6 and zinc for shiny coat.',799.00,899.00,'food','cat_food_whiskas.png',4.6,98,1,60,1,'["cat","food","oceanfish"]'),
('pr_cf_3','Sheba Premium Wet Cat Food Gravy (12x70g)','Gourmet wet cat food loaf in rich succulent gravy. Made with real fish and chicken morsels.',649.00,749.00,'food','cat_food_sheba.png',4.7,75,1,30,0,'["cat","food","wet"]'),
('pr_cf_4','Purina Friskies Seafood Sensations (1.1kg)','Tempting mix of ocean whitefish, salmon, tuna and crab flavors with garden greens for cats.',499.00,599.00,'food','cat_food_friskies.png',4.5,54,1,50,0,'["cat","food","seafood"]'),
('pr_cf_5','Me-O Kitten Salmon & Tuna Food (1.1kg)','Specially formulated dry food for growing kittens with DHA, milk powder and essential vitamins.',389.00,449.00,'food','cat_food_meo.png',4.6,61,1,40,0,'["cat","kitten","food"]'),

-- DOG FOOD (5 Products)
('pr_df_1','Pedigree Chicken & Vegetable Adult Dog Food (3kg)','Complete and balanced nutrition for adult dogs with real chicken, rice, and healthy digestive fibers.',699.00,799.00,'food','dog_food_pedigree.png',4.7,210,1,80,1,'["dog","food","chicken"]'),
('pr_df_2','Royal Canin Maxi Adult Dry Dog Food (4kg)','Tailored nutrition for large breed adult dogs (26 to 44 kg). Supports high joint health and bone strength.',2199.00,2499.00,'food','dog_food_royal_canin.png',4.9,115,1,25,1,'["dog","food","maxi"]'),
('pr_df_3','Drools Focus Superpremium Adult Dog Food (3kg)','Real chicken formula with zero corn, soy or wheat. Enhanced with DHA and omega fatty acids.',1149.00,1299.00,'food','dog_food_drools.png',4.6,88,1,35,0,'["dog","food","superpremium"]'),
('pr_df_4','Meat Up Puppy Chicken & Rice Dry Food (3kg)','Nutritious puppy food formulated with high quality protein for muscle growth and immunity boosting.',599.00,699.00,'food','dog_food_meatup.png',4.4,49,1,65,0,'["dog","puppy","food"]'),
('pr_df_5','Purepet Adult Chicken & Rice Dog Food (10kg)','Economy bulk pack dry dog food providing balanced daily meal requirements for active adult dogs.',1499.00,1799.00,'food','dog_food_purepet.png',4.5,130,1,40,0,'["dog","food","economy"]'),

-- BELT / COLLARS & LEASHES (5 Products)
('pr_bl_1','Padded Heavy-Duty Nylon Dog Harness & Leash','No-pull breathable mesh dog harness with reflective stitching and padded handle leash for safe walks.',549.00,699.00,'accessories','belt_nylon_harness.png',4.8,92,1,55,1,'["dog","harness","leash","belt"]'),
('pr_bl_2','Genuine Leather Dog Collar with Brass Buckle','Handcrafted 100% genuine leather collar with rust-proof solid brass hardware and comfortable padding.',499.00,649.00,'accessories','belt_leather_collar.png',4.7,84,1,45,1,'["dog","collar","leather","belt"]'),
('pr_bl_3','Night Reflective Adjustable Dog Collar & Leash Combo','High visibility 3M reflective nylon collar and matching 5ft leash set for safe night walking.',399.00,499.00,'accessories','belt_reflective_combo.png',4.6,67,1,70,0,'["dog","reflective","collar","belt"]'),
('pr_bl_4','Velvet Cat Collar with Safety Bell & Bowtie','Soft velvet breakaway safety cat collar with quick release buckle, golden bell, and cute detachable bowtie.',249.00,329.00,'accessories','belt_cat_bowtie_collar.png',4.7,105,1,85,0,'["cat","collar","bell","belt"]'),
('pr_bl_5','5 Meter Automatic Retractable Dog Leash Belt','One-button lock and release heavy-duty tangle-free ribbon leash for dogs up to 25kg.',799.00,999.00,'accessories','belt_retractable_leash.png',4.5,78,1,30,0,'["dog","leash","retractable","belt"]'),

-- TOYS (5 Products)
('pr_ty_1','Squeaky Durable Rubber Chew Bone Toy for Dogs','Non-toxic natural rubber bone with squeaker. Cleans teeth, massages gums, and reduces destructive chewing.',249.00,349.00,'toys','toy_squeaky_bone.png',4.6,140,1,100,1,'["dog","toy","chew"]'),
('pr_ty_2','Interactive Cat Feather Wand Teaser Toy with Bell','Flexible 36-inch wand with natural feathers and jingle bell to stimulate your cat hunting instincts.',199.00,299.00,'toys','toy_cat_feather_wand.png',4.8,112,1,120,0,'["cat","toy","teaser"]'),
('pr_ty_3','Heavy Duty Cotton Rope Knot Tug Toy for Dogs','100% natural washable cotton rope toy with 3 durable knots for tug of war and dental flossing.',299.00,399.00,'toys','toy_rope_tug.png',4.7,95,1,90,0,'["dog","toy","rope"]'),
('pr_ty_4','Catnip Filled Plush Mice Toy Set (Pack of 3)','Soft plush mice toys stuffed with organic catnip. Perfect for swatting, chasing and solo play.',229.00,299.00,'toys','toy_catnip_mice.png',4.5,82,1,110,0,'["cat","toy","catnip"]'),
('pr_ty_5','Treat Dispensing IQ Puzzle Ball Toy for Pets','Interactive slow feeder puzzle ball that dispenses dry kibble or treats as your pet rolls it.',449.00,599.00,'toys','toy_treat_puzzle_ball.png',4.7,68,1,40,1,'["dog","cat","toy","puzzle"]')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `price` = VALUES(`price`), `image` = VALUES(`image`);

-- 2.5 INDEPENDENT REFERENCE DATA
INSERT INTO `coupons` (`id`,`code`,`discount`,`type`,`min_order`,`max_discount`,`active`,`usage_limit`,`usage_count`) VALUES
('cp_1','WELCOME10',10.00,'percentage',499.00,150.00,1,1000,12),
('cp_2','FLAT100',100.00,'flat',799.00,NULL,1,500,8),
('cp_3','PET50',50.00,'flat',0.00,NULL,1,NULL,3)
ON DUPLICATE KEY UPDATE `code` = VALUES(`code`);

INSERT INTO `faqs` (`id`,`question`,`answer`,`category`,`sort_order`,`active`) VALUES
('f_1','How do I book a grooming appointment?','Login, go to Services > Grooming, pick a service, select your pet, date and time, then submit.','booking',1,1),
('f_2','What is your cancellation policy?','Free cancellation up to 24 hours before the appointment. After that a 50% fee applies.','policy',2,1),
('f_3','Do you offer home delivery?','Yes, we deliver across Chhatrapati Sambhajinagar. Orders above Rs.499 get free delivery.','orders',3,1),
('f_4','How are reward points earned?','You earn 1 point per Rs.10 spent on orders and bookings. Points can be redeemed for discounts.','rewards',4,1)
ON DUPLICATE KEY UPDATE `question` = VALUES(`question`);

INSERT INTO `adoption_listings` (`id`,`name`,`species`,`breed`,`age`,`description`,`vaccinated`,`neutered`,`status`,`contact_info`) VALUES
('al_1','Bella','dog','Indie','1 year','Sweet rescued indie dog, very playful and loves kids. Looking for a loving home.',1,1,'available','9876543210'),
('al_2','Whiskers','cat','Tabby','6 months','Cute tabby kitten found abandoned. Healthy and litter trained.',1,0,'available','9777777777')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

INSERT INTO `store_settings` (`id`,`key`,`value`) VALUES
('ss_1','store_name','Pet House'),
('ss_2','store_city','Chhatrapati Sambhajinagar'),
('ss_3','store_phone','9999999999'),
('ss_4','free_delivery_min','499'),
('ss_5','tax_rate','10'),
('set_setup','setup_complete','1')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

-- 2.6 DEPENDENT TABLE 1: PETS (depends on users)
INSERT INTO `pets` (`id`, `user_id`, `name`, `species`, `breed`, `age`, `weight`, `gender`, `notes`) VALUES
('pet_1', 'u_customer', 'Bruno', 'dog', 'Labrador', 3, 28.50, 'male', 'Friendly, loves baths'),
('pet_2', 'u_customer', 'Mimi', 'cat', 'Persian', 2, 4.20, 'female', 'Needs gentle handling'),
('pet_3', 'u_cust2', 'Rocky', 'dog', 'Beagle', 4, 12.00, 'male', 'Very active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 2.7 DEPENDENT TABLE 2: ORDERS (depends on users)
INSERT INTO `orders` (`id`, `user_id`, `total`, `subtotal`, `tax`, `discount`, `status`, `payment_method`, `address`) VALUES
('or_1', 'u_customer', 1418.00, 1299.00, 129.90, 10.90, 'delivered', 'cod', '12 Ganesh Nagar, Chhatrapati Sambhajinagar'),
('or_2', 'u_customer', 449.00, 449.00, 0.00, 0.00, 'shipped', 'cod', '12 Ganesh Nagar, Chhatrapati Sambhajinagar'),
('or_3', 'u_cust2', 199.00, 199.00, 0.00, 0.00, 'pending', 'cod', '5 CIDCO, Chhatrapati Sambhajinagar')
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

-- 2.8 DEPENDENT TABLE 3: ORDER ITEMS (depends on orders, products)
INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `price`) VALUES
('oi_1', 'or_1', 'pr_1', 1, 1299.00),
('oi_2', 'or_2', 'pr_3', 1, 449.00),
('oi_3', 'or_3', 'pr_4', 1, 199.00)
ON DUPLICATE KEY UPDATE `quantity` = VALUES(`quantity`);

-- 2.9 DEPENDENT TABLE 4: GROOMING BOOKINGS (depends on users, pets, grooming_services)
INSERT INTO `grooming_bookings` (`id`, `user_id`, `pet_id`, `service_id`, `date`, `time`, `status`, `notes`) VALUES
('gb_1', 'u_customer', 'pet_1', 'gs_2', '2025-07-25', '11:00', 'confirmed', 'Prefers morning'),
('gb_2', 'u_customer', 'pet_2', 'gs_6', '2025-07-28', '15:30', 'pending', NULL),
('gb_3', 'u_cust2', 'pet_3', 'gs_1', '2025-07-20', '10:00', 'completed', NULL)
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

-- 2.10 DEPENDENT TABLE 5: BOARDING RESERVATIONS (depends on users, pets, boarding_rooms)
INSERT INTO `boarding_reservations` (`id`, `user_id`, `pet_id`, `room_id`, `check_in`, `check_out`, `status`) VALUES
('brs_1', 'u_customer', 'pet_1', 'br_1', '2025-08-01', '2025-08-05', 'confirmed')
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

-- 2.11 DEPENDENT TABLE 6: WALKING BOOKINGS (depends on users, pets)
INSERT INTO `walking_bookings` (`id`, `user_id`, `pet_id`, `date`, `duration`, `time`, `status`) VALUES
('wb_1', 'u_customer', 'pet_1', '2025-07-26', 30, '07:00', 'pending'),
('wb_2', 'u_cust2', 'pet_3', '2025-07-22', 45, '18:00', 'completed')
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

-- 2.12 DEPENDENT TABLE 7: NOTIFICATIONS (depends on users)
INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `is_read`) VALUES
('n_1', 'u_customer', 'Booking Confirmed', 'Your grooming appointment for Bruno is confirmed for 25 Jul, 11:00 AM.', 'booking', 0),
('n_2', 'u_customer', 'Order Delivered', 'Your order #or_1 has been delivered. Enjoy!', 'order', 1),
('n_3', 'u_customer', 'Reward Earned', 'You earned 50 reward points for your recent order.', 'reward', 0)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

-- 2.13 DEPENDENT TABLE 8: SUPPORT TICKETS (depends on users)
INSERT INTO `support_tickets` (`id`, `user_id`, `subject`, `message`, `status`, `priority`, `response`) VALUES
('st_1', 'u_customer', 'Question about spa package', 'Does the spa package include nail trimming?', 'open', 'medium', NULL)
ON DUPLICATE KEY UPDATE `subject` = VALUES(`subject`);

-- 2.14 DEPENDENT TABLE 9: REVIEWS (depends on users, products)
INSERT INTO `reviews` (`id`, `user_id`, `product_id`, `rating`, `comment`) VALUES
('rv_1', 'u_customer', 'pr_1', 5, 'My dog loves this food. Coat is shinier!'),
('rv_2', 'u_customer', 'pr_7', 4, 'Gentle shampoo, works great on sensitive skin.')
ON DUPLICATE KEY UPDATE `rating` = VALUES(`rating`);

-- 2.15 DEPENDENT TABLE 10: DELIVERIES (depends on orders, users)
INSERT INTO `deliveries` (`id`, `order_id`, `partner_id`, `status`, `estimated_at`) VALUES
('dl_1', 'or_1', 'u_delivery', 'delivered', '2025-07-15 18:00'),
('dl_2', 'or_2', 'u_delivery', 'in_transit', '2025-07-20 14:00')
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

-- 2.16 DEPENDENT TABLE 11: REWARD TRANSACTIONS (depends on users)
INSERT INTO `reward_transactions` (`id`, `user_id`, `points`, `type`, `source`, `description`) VALUES
('rt_1', 'u_customer', 250, 'bonus', 'signup', 'Welcome bonus'),
('rt_2', 'u_customer', 50, 'earn', 'order', 'Earned on order or_1')
ON DUPLICATE KEY UPDATE `points` = VALUES(`points`);

-- 2.17 DEPENDENT TABLE 12: BLOG POSTS (depends on users/author_id)
INSERT INTO `blog_posts` (`id`, `author_id`, `title`, `slug`, `excerpt`, `content`, `category`, `published`, `read_time`, `tags`) VALUES
('bp_1', 'u_admin', '5 Summer Grooming Tips for Your Dog', 'summer-grooming-tips', 'Keep your dog cool and comfortable this summer with these essential grooming tips.', 'Summer heat can be tough on dogs. Here are 5 grooming tips:\n\n1. Brush regularly to remove loose fur.\n2. Bathe every 2-3 weeks with mild shampoo.\n3. Keep nails trimmed.\n4. Check ears for infections.\n5. Never shave double-coated breeds.', 'grooming', 1, 4, '["grooming","summer"]'),
('bp_2', 'u_admin', 'Choosing the Right Food for Your Cat', 'right-cat-food', 'A guide to selecting nutritious food for your feline friend.', 'Cats are obligate carnivores. Look for food with real meat as the first ingredient. Avoid fillers like corn and soy.', 'nutrition', 1, 3, '["cat","food"]')
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

SET FOREIGN_KEY_CHECKS = 1;
