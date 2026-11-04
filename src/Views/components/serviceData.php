<?php
declare(strict_types=1);

// Les cartes de la page viennent de la même base que les services publiés par les utilisateurs.
$services=[];
try {
    $query=db()->query("SELECT s.Id_services,s.title,s.price,s.description,s.unit,s.location,s.status,c.name AS category,u.first_name,u.name AS last_name,u.adresse,u.postal_code,CASE WHEN s.location IS NULL OR s.location='' OR s.location='Campus Lyon' THEN NULLIF(CONCAT_WS(' ',NULLIF(u.adresse,''),NULLIF(u.postal_code,'')),'') ELSE s.location END AS resolved_location,(SELECT m.file_path FROM Media m WHERE m.Id_services=s.Id_services ORDER BY m.Id_media LIMIT 1) AS image,(SELECT COUNT(*) FROM Favorites f WHERE f.Id_services=s.Id_services) AS likes,(SELECT ROUND(AVG(r.rating),1) FROM Reviews r JOIN Bookings b ON b.Id_bookings=r.Id_bookings WHERE b.Id_services=s.Id_services) AS rating,(SELECT COUNT(*) FROM Reviews r JOIN Bookings b ON b.Id_bookings=r.Id_bookings WHERE b.Id_services=s.Id_services) AS reviews,(SELECT r.comment FROM Reviews r JOIN Bookings b ON b.Id_bookings=r.Id_bookings WHERE b.Id_services=s.Id_services ORDER BY r.created_at DESC LIMIT 1) AS comment,(SELECT CONCAT(ru.first_name,' ',ru.name) FROM Reviews r JOIN Bookings b ON b.Id_bookings=r.Id_bookings JOIN Users ru ON ru.Id_users=r.Id_users WHERE b.Id_services=s.Id_services ORDER BY r.created_at DESC LIMIT 1) AS commenter FROM Services s JOIN Categories c ON c.Id_categories=s.Id_categories JOIN Users u ON u.Id_users=s.Id_users WHERE s.status='active' ORDER BY s.Id_services DESC");
    foreach($query->fetchAll() as $row){
        $normalized=mb_strtolower(trim((string)$row['category']));
        $slug=match(true){str_contains($normalized,'coiff')=>'coiffure',str_contains($normalized,'cours')||str_contains($normalized,'soutien')=>'cours',str_contains($normalized,'déménag')||str_contains($normalized,'demenag')=>'demenagement',str_contains($normalized,'informat')=>'informatique',str_contains($normalized,'photo')||str_contains($normalized,'créa')||str_contains($normalized,'crea')=>'photo',default=>'autres'};
        $services[]=['id'=>(int)$row['Id_services'],'category'=>$slug,'label'=>$row['category'],'title'=>$row['title'],'name'=>trim($row['first_name'].' '.$row['last_name']),'study'=>'Étudiant UniFlow','price'=>(float)$row['price'],'unit'=>$row['unit'],'rating'=>$row['rating']!==null?number_format((float)$row['rating'],1,'.',''):'0.0','reviews'=>(int)$row['reviews'],'location'=>$row['resolved_location']?:'Campus Lyon','image'=>$row['image']?:'','tag'=>'','likes'=>(int)$row['likes'],'comment'=>$row['comment']??'','commenter'=>$row['commenter']??'','description'=>$row['description']];
    }
} catch(Throwable $exception) { error_log('Chargement des services : '.$exception->getMessage()); }
?>
