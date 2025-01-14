<?php

/**
 * MailQueue.php
 * @author Saranga Abeykoon http://nterms.com
 * @author Michał Pyzra https://github.com/pyton-jajko
 */

namespace yayko\mailqueue;

use Yii;
use yii\helpers\Console;
use yii\swiftmailer\Mailer;
use yayko\mailqueue\Message;
use yayko\mailqueue\models\Queue;

/**
 * MailQueue is a sub class of [yii\switmailer\Mailer](https://github.com/yiisoft/yii2-swiftmailer/blob/master/Mailer.php)
 * which intends to replace it.
 *
 * Configuration is the same as in `yii\switmailer\Mailer` with some additional properties to control the mail queue
 *
 * ~~~
 * 	'components' => [
 * 		...
 * 		'mailqueue' => [
 * 			'class' => 'yayko\mailqueue\MailQueue',
 *			'table' => '{{%mail_queue}}',
 *			'mailsPerRound' => 10,
 *			'maxAttempts' => 3,
 * 			'transport' => [
 * 				'class' => 'Swift_SmtpTransport',
 * 				'host' => 'localhost',
 * 				'username' => 'username',
 * 				'password' => 'password',
 * 				'port' => '587',
 * 				'encryption' => 'tls',
 * 			],
 * 		],
 * 		...
 * 	],
 * ~~~
 *
 * @see http://www.yiiframework.com/doc-2.0/yii-swiftmailer-mailer.html
 * @see http://www.yiiframework.com/doc-2.0/ext-swiftmailer-index.html
 *
 * This extension replaces `yii\switmailer\Message` with `yayko\mailqueue\Message'
 * to enable queuing right from the message.
 *
 */
class MailQueue extends Mailer
{
	const NAME = 'mailqueue';

	/**
	 * @var string message default class name.
	 */
	public $messageClass = 'yayko\mailqueue\Message';

	/**
	 * @var string the name of the database table to store the mail queue.
	 */
	public $table = '{{%mail_queue}}';

	/**
	 * @var integer the default value for the number of mails to be sent out per processing round.
	 */
	public $mailsPerRound = 10;

	/**
	 * @var integer maximum number of attempts to try sending an email out.
	 */
	public $maxAttempts = 3;


	/**
	 * @var boolean Purges messages from queue after sending
	 */
	public $autoPurge = true;

	/**
	 * @var boolean Error reporting scope on running mailqueue/process
	 */
	public $errorReporting = null;

	/**
	 * Initializes the MailQueue component.
	 */
	public function init()
	{
		parent::init();
	}

	/**
	 * Sends out the messages in email queue and update the database.
	 *
	 * @return boolean true if all messages are successfully sent out
	 */
	public function process()
	{
		if ($this->errorReporting !== null) {
	            error_reporting($this->errorReporting);
	        }
		if (Yii::$app->db->getTableSchema($this->table) == null) {
			throw new \yii\base\InvalidConfigException('"' . $this->table . '" not found in database. Make sure the db migration is properly done and the table is created.');
		}

		$success = true;

		$items = Queue::find()->where(['and', ['sent_time' => NULL], ['<', 'attempts', $this->maxAttempts], ['<=', 'time_to_send', date('Y-m-d H:i:s')]])->orderBy(['created_at' => SORT_ASC])->limit($this->mailsPerRound);
		foreach ($items->each() as $item) {
            try {
                if ($message = $item->toMessage()) {
                    $attributes = ['attempts', 'last_attempt_time'];
                    if ($this->send($message)) {
                        $item->sent_time = new \yii\db\Expression('NOW()');
                        $attributes[] = 'sent_time';
                    } else {
                        $success = false;
                    }

                    $item->attempts++;
                    $item->last_attempt_time = new \yii\db\Expression('NOW()');

                    $item->updateAttributes($attributes);
                }
            } catch (\Exception $e) {
                $item->is_broken = true;
                $item->error_message = $e->getMessage();
                $item->updateAttributes(['error_message', 'is_broken']);
                Console::error($e->getMessage() . '. Check console/runtime/app.log');
            }
		}

		// Purge messages now?
		if ($this->autoPurge) {
			$this->purge();
		}

		return $success;
	}


	/**
	 * Deletes sent messages from queue.
	 *
	 * @return int Number of rows deleted
	 */

	public function purge()
	{
		return Queue::deleteAll('sent_time IS NOT NULL');
	}
}
