#拥有者
OWNER=www
#logs文件夹路径
LOGSDIR=/data/quchong2/Wormhole/storage/logs/
#更改所有log文件的owner（保险措施）
chown -R www:www /data/quchong2/Wormhole/storage/logs/*;
chmod 777 /data/quchong2/Wormhole/storage/logs/*;
