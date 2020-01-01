<?php
/**
 * [ControllerEventListener] MailSignSwitch
 *
 * @link http://www.materializing.net/
 * @author arata
 * @license MIT
 */
class MailSignSwitchControllerEventListener extends BcControllerEventListener
{
	/**
	 * 登録イベント
	 *
	 * @var array
	 */
	public $events = [
		'Mail.Mail.beforeSendEmail',
		'Mail.MailContents.beforeRender',
	];

	/**
	 * mailMailBeforeSendEmail
	 *
	 * @param CakeEvent $event
	 * @return boolean
	 */
	public function mailMailBeforeSendEmail(CakeEvent $event)
	{
		$Controller = $event->subject();

		$MailSignSwitchModel = ClassRegistry::init($this->plugin . '.MailSignSwitch');
		$mailSignSwitch = $MailSignSwitchModel->find('first', [
			'conditions' => [
				'MailSignSwitch.mail_content_id' => $Controller->dbDatas['mailContent']['MailContent']['id']
			],
			'recursive'	 => -1
		]);
		if ($mailSignSwitch) {
			// MailSignSwitchが有効状態の場合、署名内容をMailSignSwitchの内容に置き換える
			if ($mailSignSwitch['MailSignSwitch']['status']) {
				$Controller->dbDatas['mailConfig']['MailConfig'] = $mailSignSwitch['MailSignSwitch'];
				//$this->log($Controller->dbDatas['mailConfig']['MailConfig'], LOG_DEBUG);
			}
		}
		return true;
	}

	/**
	 * mailMailContentsBeforeRender
	 *
	 * @param CakeEvent $event
	 */
	public function mailMailContentsBeforeRender(CakeEvent $event)
	{
		if (!BcUtil::isAdminSystem()) {
			return;
		}

		$Controller = $event->subject();
		if (!in_array($Controller->request->params['action'], ['admin_edit', 'admin_add'])) {
			return;
		}

		$MailSignSwitchModel = ClassRegistry::init($this->plugin . '.MailSignSwitch');

		// 署名切替えの入力欄に、placeholder で現在の基本設定の内容を表示するためにデータを送る
		// - first は最初の1レコードを取得するためID指定は不要
		$MailConfigModel = ClassRegistry::init('Mail.MailConfig');
		$mailConfigData = $MailConfigModel->find('first', ['recursive' => -1]);
		$Controller->request->data['MailConfig'] = $mailConfigData['MailConfig'];

		if ($Controller->request->params['action'] == 'admin_add') {
			// メールフォーム追加画面では、MailSignSwitchの初期設定情報を送る
			$defalut = $MailSignSwitchModel->getDefaultValue();
			$Controller->request->data['MailSignSwitch'] = $defalut['MailSignSwitch'];
			return;
		}

		if (isset($Controller->request->data['MailSignSwitch']) && empty($Controller->request->data['MailSignSwitch']['id'])) {
			$default = $MailSignSwitchModel->getDefaultValue();
			$Controller->request->data['MailSignSwitch'] = $default['MailSignSwitch'];
		}
	}

}
