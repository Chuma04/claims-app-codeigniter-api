<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class Insertclaimtypes extends Migration
{
   public function up()
   {
       $data = [
           ['name' => 'Home Incident', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
           ['name' => 'Travel Medical', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
           ['name' => 'Auto Accident', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
       ];

       $this->db->table('claim_types')->insertBatch($data);
   }

   public function down()
   {
       $this->db->table('claim_types')->whereIn('name', ['Home Incident', 'Travel Medical', 'Auto Accident'])->delete();
   }
}
