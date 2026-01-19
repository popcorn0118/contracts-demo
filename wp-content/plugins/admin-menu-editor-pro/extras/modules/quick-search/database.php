<?php

namespace YahnisElsts\AdminMenuEditor\QuickSearch;

class DbAdapter {
	public function updateCrawlRecords($records, $stopOnError = false) {
		global $wpdb;
		$tableName = $this->getCrawlerTableName();

		if ( !$this->tableExists($tableName) ) {
			$creationResult = $this->createCrawlerTable();
			if ( is_wp_error($creationResult) ) {
				return $creationResult;
			}
		}

		//Some of the records might be new, some might be updates.
		//We need to insert new records and update existing ones.
		$existingRecords = $this->fetchCrawlRecords(array_keys($records));

		$inserted = 0;
		$updated = 0;
		$errors = new \WP_Error();

		$columnFormats = [
			'url'                       => '%s',
			'isMenuItem'                => '%d',
			'lastAttemptAt'             => '%d',
			'lastAttemptStatus'         => '%s',
			'lastFinishedAttemptAt'     => '%d',
			'lastFinishedAttemptStatus' => '%s',
			'depth'                     => '%d',
			'reason'                    => '%s',
			'componentAndVersion'       => '%s',
			'errorMessage'              => '%s',
		];

		//phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		//This will probably go faster if we use a transaction.
		$wpdb->query('START TRANSACTION');

		foreach ($records as $url => $record) {
			$isUpdate = isset($existingRecords[$url]);
			$values = [];
			$formats = [];

			//Convert isMenuItem from a boolean to an integer.
			if ( isset($record['isMenuItem']) ) {
				$record['isMenuItem'] = $record['isMenuItem'] ? 1 : 0;
			}

			foreach ($columnFormats as $column => $format) {
				if ( $isUpdate && ($column === 'url') ) {
					continue;
				}

				if ( array_key_exists($column, $record) ) {
					$values[$column] = $record[$column];
					$formats[] = $format;
				}
			}

			if ( isset($existingRecords[$url]) ) {
				$where = ['url' => $url];
				if ( $wpdb->update($tableName, $values, $where, $formats, ['%s']) !== false ) {
					$updated++;
				} else {
					$errors->add(
						'update_error',
						sprintf('Error updating record for URL %s: %s', $url, $wpdb->last_error)
					);
					if ( $stopOnError ) {
						$wpdb->query('ROLLBACK');
						//Since we rolled back, inserted and updated counts should go to zero
						//because those changes were not committed.
						return [
							'inserted' => 0,
							'updated'  => 0,
							'errors'   => $errors,
						];
					}
				}
			} else {
				if ( $wpdb->insert($tableName, $values, $formats) ) {
					$inserted++;
				} else {
					$errors->add(
						'insert_error',
						sprintf('Error inserting record for URL %s: %s', $url, $wpdb->last_error)
					);
					if ( $stopOnError ) {
						$wpdb->query('ROLLBACK');
						return [
							'inserted' => 0,
							'updated'  => 0,
							'errors'   => $errors,
						];
					}
				}
			}
		}

		$wpdb->query('COMMIT');
		//phpcs:enable

		return [
			'inserted' => $inserted,
			'updated'  => $updated,
			'errors'   => $errors,
		];
	}

	//phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	public function fetchCrawlRecords($urls) {
		global $wpdb;
		$tableName = $this->getCrawlerTableName();
		//Check if the table exists. It might not be created until it's needed.
		if ( !$this->tableExists($tableName) ) {
			return [];
		}

		$records = [];
		$batchSize = 50;

		$integerColumns = [
			'isMenuItem',
			'lastAttemptAt',
			'lastFinishedAttemptAt',
			'depth',
		];

		while (!empty($urls)) {
			$batch = array_splice($urls, 0, $batchSize);
			$placeholders = implode(',', array_fill(0, count($batch), '%s'));
			$query = "SELECT * FROM $tableName WHERE url IN ($placeholders)";
			//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Placeholders are dynamically generated.
			$preparedQuery = $wpdb->prepare($query, $batch);

			//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared above.
			$results = $wpdb->get_results($preparedQuery, ARRAY_A);
			if ( !empty($results) ) {
				foreach ($results as $result) {
					foreach ($integerColumns as $column) {
						if ( isset($result[$column]) ) {
							$result[$column] = (int)$result[$column];
						}
					}

					$records[$result['url']] = $result;
				}
			}
		}

		return $records;
	}

	public function setFoundDashboardItemsFor($menuUrl, $items, $stopOnError = false) {
		//The URL should not be empty.
		if ( empty($menuUrl) ) {
			return [0, new \WP_Error('empty_menu_url', 'The menu URL cannot be empty.')];
		}

		$tableName = $this->getItemTableName();
		if ( !$this->tableExists($tableName) ) {
			$creationResult = $this->createItemsTable();
			if ( is_wp_error($creationResult) ) {
				return [0, $creationResult];
			}
		}

		global $wpdb;
		$wpdb->query('START TRANSACTION');

		//Fetch existing items so that we can preserve the "createdAt" and "lastKnownUseAt" values.
		$existingItemRows = $wpdb->get_results(
			$wpdb->prepare(
			//  phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Preparing table names would require WP 6.2+.
				"SELECT * FROM `$tableName` WHERE menuUrl = %s",
				$menuUrl
			),
			ARRAY_A
		);
		//Index the existing items by their relative ID.
		$existingItems = [];
		foreach ($existingItemRows as $row) {
			$existingItems[$row['relativeId']] = $row;
		}

		//Delete all old items.
		$wpdb->delete($tableName, ['menuUrl' => $menuUrl]);

		//Insert new items.
		$inserted = 0;
		$now = current_time('mysql', true);
		$errors = new \WP_Error();

		foreach ($items as $item) {
			$relativeId = (string)$item['relativeId'];
			$createdAt = $now;
			$lastKnownUseAt = null;

			//Note how the timestamps are taken from the existing items, not the new ones.
			//The new serialized items generally won't include timestamps.
			if ( isset($existingItems[$relativeId]) ) {
				if ( !empty($existingItems[$relativeId]['createdAt']) ) {
					$createdAt = $existingItems[$relativeId]['createdAt'];
				}
				if ( !empty($existingItems[$relativeId]['lastKnownUseAt']) ) {
					$lastKnownUseAt = $existingItems[$relativeId]['lastKnownUseAt'];
				}
			}

			//We don't need to store "type" because it's always "dashboardItem" for these items.
			unset($item['type']);

			$searchableText = (string)$item['label'];
			$serializedProps = wp_json_encode($item);

			$success = $wpdb->insert(
				$tableName,
				[
					'menuUrl'               => $menuUrl,
					'relativeId'            => $relativeId,
					'createdAt'             => $createdAt,
					'lastSeenDuringCrawlAt' => $now,
					'lastKnownUseAt'        => $lastKnownUseAt,
					'searchableText'        => $searchableText,
					'serializedProps'       => $serializedProps,
				],
				'%s'
			);

			if ( $success ) {
				$inserted++;
			} else if ( $success === false ) {
				$errors->add('insert_error', sprintf('Error inserting item %s: %s', $relativeId, $wpdb->last_error));
				if ( $stopOnError ) {
					$wpdb->query('ROLLBACK');
					return [$inserted, $errors];
				}
			}
		}

		$wpdb->query('COMMIT');

		return [$inserted, $errors];
	}

	/**
	 * @return DashboardItemDefinition[]
	 */
	public function getRecentlyUsedDashboardItems(int $maxResults): array {
		if ( $maxResults <= 0 ) {
			return [];
		}

		global $wpdb;
		$tableName = $this->getItemTableName();
		if ( !$this->tableExists($tableName) ) {
			return [];
		}

		$recentlyUsedThreshold = strtotime('-2 month');
		$recentlyUsedThreshold = gmdate('Y-m-d H:i:s', $recentlyUsedThreshold);

		$preparedQuery = $wpdb->prepare(
		//   phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Preparing table names requires WP 6.2+.
			"SELECT * FROM `$tableName` WHERE lastKnownUseAt >= %s ORDER BY lastKnownUseAt DESC LIMIT %d",
			$recentlyUsedThreshold,
			$maxResults
		);

		//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared above.
		$results = $wpdb->get_results($preparedQuery, ARRAY_A);
		if ( empty($results) ) {
			return [];
		}

		return array_map([$this, 'unserializeDashboardItem'], $results);
	}

	public function updateRecentlyUsedDashboardItems($updates) {
		if ( empty($updates) ) {
			return;
		}

		$tableName = $this->getItemTableName();
		if ( !$this->tableExists($tableName) ) {
			return;
		}

		global $wpdb;
		$wpdb->query('START TRANSACTION');

		foreach ($updates as $update) {
			$menuUrl = $update['menuUrl'];
			$relativeId = $update['relativeId'];
			$lastKnownUseAt = gmdate('Y-m-d H:i:s', $update['timestamp']);

			//Set lastKnownUseAt either to the maximum of the existing value and the new value,
			//or to the new value if there was no existing value.
			$query = $wpdb->prepare(
			//   phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"UPDATE `$tableName` 
					 SET lastKnownUseAt = GREATEST(IFNULL(lastKnownUseAt, '2024-01-01 00:00:00'), %s) 
					 WHERE menuUrl = %s AND relativeId = %s",
				$lastKnownUseAt,
				$menuUrl,
				$relativeId
			);
			//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared above, separately so it can be logged if needed.
			$success = $wpdb->query($query);

			if ( $success === false ) {
				$wpdb->query('ROLLBACK');
				return;
			}
		}

		$wpdb->query('COMMIT');
	}

	/**
	 * @param string $query
	 * @return DashboardItemDefinition[]
	 */
	public function searchDashboardItems($query, $maxResults) {
		global $wpdb;

		$itemTableName = $this->getItemTableName();
		if ( !$this->tableExists($itemTableName) ) {
			return [];
		}

		//Remove special characters used in boolean search.
		$query = trim(str_replace(['+', '*'], '', $query));

		$searchTerms = preg_split('@\s++@', $query, 20, PREG_SPLIT_NO_EMPTY);
		if ( empty($searchTerms) ) {
			return [];
		}

		$booleanTerms = [];
		foreach ($searchTerms as $term) {
			//Add '+' for AND logic and '*' for prefix matching.
			$booleanTerms[] = '+' . esc_sql($term) . '*';
		}

		$fullTextQuery = implode(' ', $booleanTerms);
		$results = $wpdb->get_results(
			$wpdb->prepare(
			//   phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM `$itemTableName` 
                 WHERE MATCH(searchableText) AGAINST (%s IN BOOLEAN MODE)
                 LIMIT %d",
				$fullTextQuery,
				$maxResults
			),
			ARRAY_A
		);

		if ( empty($results) ) {
			return [];
		}
		return array_map([$this, 'unserializeDashboardItem'], $results);
	}

	public function getItemsByRefs(array $itemRefs): array {
		if ( empty($itemRefs) ) {
			return [];
		}

		global $wpdb;
		$tableName = $this->getItemTableName();
		if ( !$this->tableExists($tableName) ) {
			return [];
		}

		$query = "SELECT * FROM `$tableName` WHERE ";
		$conditions = [];
		$params = [];
		foreach ($itemRefs as $ref) {
			if ( isset($ref['menuUrl'], $ref['relativeId']) ) {
				$conditions[] = "(menuUrl = %s AND relativeId = %s)";
				$params[] = $ref['menuUrl'];
				$params[] = $ref['relativeId'];
			}
		}

		if ( empty($conditions) ) {
			return [];
		}

		$query .= implode(' OR ', $conditions);
		//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query with placeholders is dynamically generated.
		$preparedQuery = $wpdb->prepare($query, ...$params);

		//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared above.
		$results = $wpdb->get_results($preparedQuery, ARRAY_A);
		if ( empty($results) ) {
			return [];
		}

		return array_map([$this, 'unserializeDashboardItem'], $results);
	}

	private function unserializeDashboardItem($tableRow) {
		$props = json_decode($tableRow['serializedProps'], true);
		if ( !is_array($props) ) {
			throw new \RuntimeException(
				'Failed to unserialize dashboard item properties: '
				. json_last_error_msg() . ' (' . json_last_error() . ')'
			);
		}

		return new DashboardItemDefinition(
			$props['label'],
			new DashboardItemOrigin(
				$tableRow['menuUrl'],
				\ameUtils::get($props, ['origin', 'pageUrl'], null)
			),
			new DashboardItemTarget(
				$props['target']['type'],
				\ameUtils::get($props, ['target', 'url'], ''),
				\ameUtils::get($props, ['target', 'selector'], '')
			),
			$tableRow['relativeId'],
			\ameUtils::get($props, 'location', [])
		);
	}

	private function getCrawlerTableName() {
		global $wpdb;
		return $wpdb->prefix . 'ame_qs_crawler';
	}

	private function tableExists($fullTableName) {
		global $wpdb;
		//WP doesn't have a built-in function to check if a table exists, so we have to use a direct query.
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $fullTableName)) === $fullTableName;
	}


	private function createCrawlerTable() {
		if ( !$this->serverSupportsInnoDB() ) {
			return new \WP_Error('innodb_not_supported', 'This feature requires the InnoDB storage engine.');
		}

		if ( !$this->serverSupportsRealUtf8() ) {
			return new \WP_Error('utf8mb4_not_supported', 'This feature requires the utf8mb4_bin collation.');
		}

		$tableName = $this->getCrawlerTableName();

		$sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
			url varchar(500) NOT NULL,
			isMenuItem tinyint(1) NOT NULL DEFAULT 0,
			lastAttemptAt int(10) UNSIGNED DEFAULT NULL,
			lastAttemptStatus varchar(20) DEFAULT NULL,
			lastFinishedAttemptAt int(10) UNSIGNED DEFAULT NULL,
			lastFinishedAttemptStatus varchar(20) DEFAULT NULL,
			depth smallint(5) UNSIGNED NOT NULL DEFAULT 0,
			reason varchar(300) DEFAULT NULL,
			componentAndVersion varchar(300) DEFAULT NULL,
			errorMessage varchar(500) DEFAULT NULL,
			PRIMARY KEY  (url)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;";

		global $wpdb;
		//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- CREATE TABLE doesn't have any user input.
		$success = $wpdb->query($sql);
		if ( $success === false ) {
			return new \WP_Error('crawler_table_creation_failed', $wpdb->last_error);
		}
		return true;
	}

	public function serverSupportsInnoDB() {
		global $wpdb;
		/** @noinspection SqlResolve */
		$status = $wpdb->get_var("SELECT SUPPORT FROM INFORMATION_SCHEMA.ENGINES WHERE ENGINE = 'InnoDB'");
		return (($status === 'YES') || ($status === 'DEFAULT'));
	}


	public function serverSupportsRealUtf8() {
		global $wpdb;
		$rows = $wpdb->get_results("SHOW COLLATION LIKE 'utf8mb4_bin'", ARRAY_A);
		return !empty($rows);
	}


	private function createItemsTable() {
		if ( !$this->serverSupportsInnoDB() ) {
			return new \WP_Error('innodb_not_supported', 'This feature requires the InnoDB storage engine.');
		}

		if ( !$this->serverSupportsRealUtf8() ) {
			return new \WP_Error('utf8mb4_not_supported', 'This feature requires the utf8mb4_bin collation.');
		}

		global $wpdb;
		$tableName = $this->getItemTableName();

		$sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
			  `menuUrl` varchar(250) NOT NULL,
			  `relativeId` varchar(200) NOT NULL,
			  `createdAt` datetime NOT NULL,
			  `lastSeenDuringCrawlAt` datetime NOT NULL,
			  `lastKnownUseAt` datetime DEFAULT NULL,
			  `searchableText` varchar(2000) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
			  `serializedProps` text NOT NULL,
			  UNIQUE KEY `idx_ame_item_unique_id` (`menuUrl`, `relativeId`),
			  KEY `idx_qs_last_used` (`lastKnownUseAt`),
			  FULLTEXT KEY `searchableText` (`searchableText`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;";

		//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$success = $wpdb->query($sql);
		if ( $success === false ) {
			return new \WP_Error('items_table_creation_failed', $wpdb->last_error);
		}
		return true;
	}

	private function getItemTableName() {
		global $wpdb;
		return $wpdb->prefix . 'ame_qs_items';
	}

	//phpcs:enable

	/**
	 * Delete crawler records and items that haven't been crawled or seen for more
	 * than the specified number of days.
	 *
	 * @param int $crawlRecordThresholdInDays
	 * @param int $itemThresholdInDays
	 * @return array<string,int>|\WP_Error
	 */
	public function deleteStaleEntries($crawlRecordThresholdInDays, $itemThresholdInDays) {
		$crawlerTableName = $this->getCrawlerTableName();
		$itemTableName = $this->getItemTableName();

		//Tables are not created until they're needed, so they might not exist.
		$crawlerTableExists = $this->tableExists($crawlerTableName);
		$itemTableExists = $this->tableExists($itemTableName);
		if ( !$crawlerTableExists && !$itemTableExists ) {
			return [
				'deletedCrawlRecords' => 0,
				'deletedItems'        => 0,
			];
		}

		global $wpdb;
		//phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query('START TRANSACTION');

		$now = time();
		$staleCrawlRecordTimestamp = $now - $crawlRecordThresholdInDays * DAY_IN_SECONDS;
		$staleItemTimestamp = $now - $itemThresholdInDays * DAY_IN_SECONDS;

		$deletedCrawlRecords = 0;
		$deletedItems = 0;

		if ( $crawlerTableExists ) {
			$deletedCrawlRecords = $wpdb->query(
				$wpdb->prepare(
				//   phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"DELETE FROM `$crawlerTableName` WHERE lastAttemptAt < %d",
					$staleCrawlRecordTimestamp
				)
			);
			if ( $deletedCrawlRecords === false ) {
				$lastError = $wpdb->last_error;
				$wpdb->query('ROLLBACK');
				return new \WP_Error('delete_error', 'Error deleting stale crawl records. ' . $lastError);
			}
		}

		if ( $itemTableExists ) {
			$deletedItems = $wpdb->query(
				$wpdb->prepare(
				//   phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"DELETE FROM `$itemTableName` WHERE lastSeenDuringCrawlAt < %s",
					gmdate('Y-m-d H:i:s', $staleItemTimestamp)
				)
			);

			if ( $deletedItems === false ) {
				$lastError = $wpdb->last_error;
				$wpdb->query('ROLLBACK');
				return new \WP_Error('delete_error', 'Error deleting stale items. ' . $lastError);
			}
		}

		$wpdb->query('COMMIT');
		//phpcs:enable

		return [
			'deletedCrawlRecords' => $deletedCrawlRecords,
			'deletedItems'        => $deletedItems,
		];
	}
}

class DashboardSearchEngine implements ItemSearchEngine {
	private $dbAdapter;
	private $menuUrlLookup;

	public function __construct(DbAdapter $dbAdapter, array $menuUrlLookup) {
		$this->dbAdapter = $dbAdapter;
		$this->menuUrlLookup = $menuUrlLookup;
	}

	public function getRecentItems(array $itemRefs, int $desiredResults = 20): array {
		$referencedItems = $this->dbAdapter->getItemsByRefs($itemRefs);
		$referencedItems = $this->filterPresentItems($referencedItems);
		if ( count($referencedItems) >= $desiredResults ) {
			return array_slice($referencedItems, 0, $desiredResults);
		}

		//If we didn't get enough items that way, fill the rest with recently used items.
		$recentItems = $this->dbAdapter->getRecentlyUsedDashboardItems($desiredResults - count($referencedItems));
		$recentItems = $this->filterPresentItems($recentItems);

		//Deduplicate items that were already included in the referenced items.
		$mergedResults = [];
		$usedKeys = [];
		foreach ($referencedItems as $item) {
			$mergedResults[] = $item;
			$usedKeys[$item->getUniqueId()] = true;
		}
		foreach ($recentItems as $item) {
			$key = $item->getUniqueId();
			if ( !isset($usedKeys[$key]) ) {
				$mergedResults[] = $item;
				$usedKeys[$key] = true;
			}
		}

		return array_slice($mergedResults, 0, $desiredResults);
	}

	public function searchItems(string $query, int $maxResults = 100): array {
		//We need to filter the results against the present menu URLs, but using an IN clause
		//with a huge number of items is not efficient (on some sites, the admin menu can have
		//hundreds of items). Instead, we request more results than we need, then filter in PHP.
		//Hopefully, a reasonable query won't return too many results.
		$requestedResults = $maxResults * 2 + 1;

		$results = $this->dbAdapter->searchDashboardItems($query, $requestedResults);
		$results = $this->filterPresentItems($results);
		return array_slice($results, 0, $maxResults);
	}

	/**
	 * @param DashboardItemDefinition[] $items
	 * @return DashboardItemDefinition[]
	 */
	private function filterPresentItems(array $items): array {
		return array_filter($items, function (DashboardItemDefinition $item) {
			$url = $item->getMenuUrl();
			if ( !is_string($url) ) {
				return true; //Should not happen, but we'll include the item just in case.
			}
			return isset($this->menuUrlLookup[$url]);
		});
	}
}