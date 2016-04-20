USE `ybk`;

TRUNCATE `ybk_notes`;

INSERT INTO `ybk_notes` VALUES (
  1, 'restaurant-1', 'edfward',
  'context-1 context-1 context-1 context-1 context-1 ',
  'review-1 review-1 review-1 review-1 review-1',
  NULL
), (
  2, 'restaurant-2', 'edfward',
  'context-2 context-2 context-2 context-2 context-2 ',
  'review-2 review-2 review-2 review-2 review-2',
  'good'
);
