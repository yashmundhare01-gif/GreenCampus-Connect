<?php
require_once __DIR__ . '/helpers.php';
$pdo=db();

if($_SERVER['REQUEST_METHOD']==='GET'){
 require_admin(); $where=[];$params=[];
 $status=clean_string($_GET['status']??'',30); $category=(int)($_GET['category']??0); $location=(int)($_GET['location']??0);
 $priority=clean_string($_GET['priority']??'',20); $date=clean_string($_GET['date']??'',10);
 if($status!==''){$where[]='r.status=?';$params[]=$status;} if($category){$where[]='r.category_id=?';$params[]=$category;}
 if($location){$where[]='r.location_id=?';$params[]=$location;} if($priority!==''){$where[]='r.priority=?';$params[]=$priority;}
 if($date!==''&&preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)){$where[]='DATE(r.created_at)=?';$params[]=$date;}
 $sql="SELECT r.id,r.report_code,r.description,r.image_path,r.priority,r.status,r.resolution_remarks,r.created_at,r.updated_at,r.resolved_at,
 u.reporter_type,u.name AS reporter,u.department AS reporter_department,u.contact,c.name AS category,l.name AS location,d.name AS assigned_department
 FROM reports r JOIN users u ON u.id=r.user_id JOIN categories c ON c.id=r.category_id JOIN locations l ON l.id=r.location_id
 LEFT JOIN departments d ON d.id=r.assigned_department_id";
 if($where)$sql.=' WHERE '.implode(' AND ',$where); $sql.=' ORDER BY r.created_at DESC';
 $st=$pdo->prepare($sql);$st->execute($params);
 $stats=$pdo->query("SELECT COUNT(*) total,SUM(status='Pending') pending,SUM(status='In Progress') in_progress,SUM(status='Resolved') resolved FROM reports")->fetch();
 $cat=$pdo->query("SELECT c.name label,COUNT(*) value FROM reports r JOIN categories c ON c.id=r.category_id GROUP BY c.id,c.name ORDER BY value DESC")->fetchAll();
 $loc=$pdo->query("SELECT l.name label,COUNT(*) value FROM reports r JOIN locations l ON l.id=r.location_id GROUP BY l.id,l.name ORDER BY value DESC")->fetchAll();
 $mon=$pdo->query("SELECT DATE_FORMAT(created_at,'%Y-%m') label,COUNT(*) value FROM reports GROUP BY DATE_FORMAT(created_at,'%Y-%m') ORDER BY label ASC LIMIT 12")->fetchAll();
 json_response(['success'=>true,'data'=>$st->fetchAll(),'stats'=>$stats,'analytics'=>['category'=>$cat,'location'=>$loc,'monthly'=>$mon]]);
}

if($_SERVER['REQUEST_METHOD']==='POST'){
 $type=clean_string($_POST['reporter_type']??'',20); $name=clean_string($_POST['name']??'',120);
 $dept=clean_string($_POST['department']??'',120); $contact=clean_string($_POST['contact']??'',120);
 $description=trim((string)($_POST['description']??'')); $cat=(int)($_POST['category_id']??0); $loc=(int)($_POST['location_id']??0);
 $priority=clean_string($_POST['priority']??'Medium',20);
 validate_enum($type,['Student','Staff'],'reporter type'); validate_enum($priority,['Low','Medium','High'],'priority');
 if($name===''||$dept===''||$contact===''||$description==='')json_response(['success'=>false,'message'=>'Please fill all required fields'],422);
 if(strlen($description)<3)json_response(['success'=>false,'message'=>'Please enter a problem description'],422);
 if(!$cat||!$loc)json_response(['success'=>false,'message'=>'Please select category and location'],422);
 $q=$pdo->prepare('SELECT COUNT(*) FROM categories WHERE id=?');$q->execute([$cat]);if(!(int)$q->fetchColumn())json_response(['success'=>false,'message'=>'Invalid category'],422);
 $q=$pdo->prepare('SELECT COUNT(*) FROM locations WHERE id=?');$q->execute([$loc]);if(!(int)$q->fetchColumn())json_response(['success'=>false,'message'=>'Invalid location'],422);
 $image=isset($_FILES['photo'])?save_uploaded_image($_FILES['photo']):null;
 try{
  $pdo->beginTransaction();
  $u=$pdo->prepare('INSERT INTO users(reporter_type,name,department,contact) VALUES(?,?,?,?)');$u->execute([$type,$name,$dept,$contact]);$uid=(int)$pdo->lastInsertId();
  // Insert first, get the real auto-increment ID, then turn it into GC-0001 style.
  $temp='TEMP-'.bin2hex(random_bytes(8));
  $r=$pdo->prepare('INSERT INTO reports(report_code,user_id,category_id,location_id,description,image_path,priority,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,"Pending",NOW(),NOW())');
  $r->execute([$temp,$uid,$cat,$loc,$description,$image,$priority]);
  $rid=(int)$pdo->lastInsertId(); $code='GC-'.str_pad((string)$rid,4,'0',STR_PAD_LEFT);
  $u2=$pdo->prepare('UPDATE reports SET report_code=? WHERE id=?');$u2->execute([$code,$rid]);
  $pdo->commit();
  json_response(['success'=>true,'message'=>'Report submitted successfully','report_id'=>$code],201);
 }catch(Throwable $e){
  if($pdo->inTransaction())$pdo->rollBack();
  json_response(['success'=>false,'message'=>'Could not save report. MySQL/database setup failed or the reports table is incompatible. Restart MySQL and reload the page.'],500);
 }
}

if($_SERVER['REQUEST_METHOD']==='PATCH'){
 require_admin();$d=get_json_input();$id=(int)($d['id']??0);$status=clean_string($d['status']??'',30);$did=(int)($d['assigned_department_id']??0);$remarks=clean_string($d['resolution_remarks']??'',3000);
 if(!$id)json_response(['success'=>false,'message'=>'Invalid report'],422);validate_enum($status,['Pending','In Progress','Resolved'],'status');
 $pdo->beginTransaction();
 try{
  $s=$pdo->prepare('SELECT status FROM reports WHERE id=? FOR UPDATE');$s->execute([$id]);$old=$s->fetchColumn();
  if($old===false){$pdo->rollBack();json_response(['success'=>false,'message'=>'Report not found'],404);}
  if($did){$q=$pdo->prepare('SELECT COUNT(*) FROM departments WHERE id=?');$q->execute([$did]);if(!(int)$q->fetchColumn()){ $pdo->rollBack();json_response(['success'=>false,'message'=>'Invalid department'],422);}}else{$did=null;}
  $resolved=$status==='Resolved'?date('Y-m-d H:i:s'):null;
  $q=$pdo->prepare('UPDATE reports SET status=?,assigned_department_id=?,resolution_remarks=?,resolved_at=?,updated_at=NOW() WHERE id=?');$q->execute([$status,$did,$remarks?:null,$resolved,$id]);
  if($old!==$status){$q=$pdo->prepare('INSERT INTO report_status_history(report_id,old_status,new_status,remarks,changed_by) VALUES(?,?,?,?,?)');$q->execute([$id,$old,$status,$remarks?:null,$_SESSION['admin_id']]);}
  $pdo->commit();json_response(['success'=>true,'message'=>'Report updated successfully']);
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();json_response(['success'=>false,'message'=>'Could not update report'],500);}
}
json_response(['success'=>false,'message'=>'Unsupported request'],405);
