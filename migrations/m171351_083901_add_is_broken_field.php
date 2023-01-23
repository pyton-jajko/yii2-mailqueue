<?php

namespace nterms\mailqueue\migrations;

use Yii;
use yii\db\Schema;
use yii\db\Migration;
use nterms\mailqueue\MailQueue;

class m171351_083901_add_is_broken_field extends Migration
{

    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('mail_queue', 'is_broken', $this->boolean()->defaultValue(false));
        $this->addColumn('mail_queue', 'error_message', $this->string(2048));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('mail_queue', 'is_broken');
        $this->dropColumn('mail_queue', 'error_message');
    }

}
