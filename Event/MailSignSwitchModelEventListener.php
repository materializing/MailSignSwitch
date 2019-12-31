<?php
/**
 * [ModelEventListener] MailSignSwitch
 *
 * @link http://www.materializing.net/
 * @author arata
 * @license MIT
 */
class MailSignSwitchModelEventListener extends BcModelEventListener
{
	/**
	 * 登録イベント
	 *
	 * @var array
	 */
	public $events = [
		'Mail.MailContent.beforeFind',
		'Mail.MailContent.afterSave',
		'Mail.MailContent.afterDelete',
	];

	/**
	 * メールサインスイッチ設定モデル
	 *
	 * @var Object
	 */
	public $MailSignSwitchModel = null;

	/**
	 * プラグインのモデル名
	 *
	 * @var string
	 */
	private $pluginModelName = 'MailSignSwitch';

	/**
	 * MailSignSwitch モデルを準備する
	 *
	 */
	private function setUpModel()
	{
		if (ClassRegistry::isKeySet($this->plugin . '.' . $this->pluginModelName)) {
			$this->MailSignSwitchModel = ClassRegistry::getObject($this->plugin . '.' . $this->pluginModelName);
		} else {
			$this->MailSignSwitchModel = ClassRegistry::init($this->plugin . '.' . $this->pluginModelName);
		}
	}

	/**
	 * mailMailContentBeforeFind
	 * メールコンテンツ取得の際にメールレシーバースイッチ情報も併せて取得する
	 *
	 * @param CakeEvent $event
	 */
	public function mailMailContentBeforeFind(CakeEvent $event)
	{
		$Model		 = $event->subject();
		$association = [
			$this->pluginModelName => [
				'className'	 => $this->plugin . '.' . $this->pluginModelName,
				'foreignKey' => 'mail_content_id'
			]
		];
		$Model->bindModel(['hasOne' => $association]);
	}

	/**
	 * mailMailContentAfterSave
	 * メールコンテンツ保存時にメールレシーバースイッチ情報も保存する
	 *
	 * @param CakeEvent $event
	 */
	public function mailMailContentAfterSave(CakeEvent $event)
	{
		$Model = $event->subject();

		// MailSignSwitch のデータがない場合は save 処理を実施しない
		if (!isset($Model->data[$this->pluginModelName]) || empty($Model->data[$this->pluginModelName])) {
			return;
		}

		$saveData = $this->generateContentSaveData($Model, $Model->id);
		if ($saveData) {
			if (!$this->MailSignSwitchModel->save($saveData)) {
				$this->log(sprintf('ID：%s のメールサインスイッチ設定の保存に失敗しました。', $Model->data[$this->pluginModelName]['id']));
			}
		}
	}

	/**
	 * mailMailContentAfterDelete
	 * メールコンテンツ削除時にメールレシーバースイッチ情報も削除する
	 *
	 * @param CakeEvent $event
	 */
	public function mailMailContentAfterDelete(CakeEvent $event)
	{
		$this->setUpModel();
		$Model	 = $event->subject();
		$data	 = $this->MailSignSwitchModel->find('first', [
			'conditions' => [$this->pluginModelName . '.mail_content_id' => $Model->id],
			'recursive'	 => -1
		]);
		if ($data) {
			if (!$this->MailSignSwitchModel->delete($data[$this->pluginModelName]['id'])) {
				$this->log('ID:' . $data[$this->pluginModelName]['id'] . 'のメールレシーバースイッチ設定の削除に失敗しました。');
			}
		}
	}

	/**
	 * 保存するデータの生成
	 *
	 * @param Object $Model
	 * @param int $contentId
	 * @return boolean or array
	 */
	private function generateContentSaveData($Model, $contentId)
	{
		if ($Model->alias != 'MailContent') {
			return false;
		}

		$this->setUpModel();
		$params		 = Router::getParams();   // 動作判定のため取得
		$data		 = [];   // 保存対象データ
		$foreignId	 = $contentId; // 外部キー設定
		if (isset($params['pass'][0])) {
			$oldModelId = $params['pass'][0]; // ajax処理に入ってくる処理対象モデルID
		}

		switch ($params['action']) {
			case 'admin_add':  // メールフォーム追加時
				$data[$this->pluginModelName]					 = $Model->data[$this->pluginModelName];
				$data[$this->pluginModelName]['mail_content_id'] = $foreignId;
				unset($data[$this->pluginModelName]['id']);
				break;

			case 'admin_edit':  // メールフォーム編集時
				$data[$this->pluginModelName]					 = $Model->data[$this->pluginModelName];
				$data[$this->pluginModelName]['mail_content_id'] = $foreignId;
				break;

			case 'admin_ajax_copy': // Ajaxコピー処理時に実行
				// メールフォームコピー保存時にエラーがなければ保存処理を実行
				if (empty($Model->validationErrors)) {
					// ajax 処理の際は、複製するモデルのデータのみ取得している状態なので、プラグイン側では改めて複製対象を取得する
					$cloneData = $this->MailSignSwitchModel->find('first', [
						'conditions' => [
							$this->pluginModelName . '.mail_content_id' => $oldModelId
						],
						'recursive'	 => -1
					]);
					if ($cloneData) {
						// コピー元データがある時
						$data											 = Hash::merge($data, $cloneData);
						$data[$this->pluginModelName]['mail_content_id'] = $contentId;
						unset($data[$this->pluginModelName]['id']);
					} else {
						// コピー元データがない時
						$data[$this->pluginModelName]['mail_content_id'] = $foreignId;
					}
				}
				break;

			default:
				break;
		}

		return $data;
	}

}
