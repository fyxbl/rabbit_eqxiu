#!/bin/bash

for((i=1;i<=720;i++));
do
nohup php think queue:work --queue rabbit --daemon &

sleep 1h

PID=$(ps -ef | grep 'php think queue:work --queue rabbit --daemon'| grep -v grep | awk '{print $2}')

if [ $? -eq 0 ]; then
    echo "process id:$PID"
else
    echo "process $input1 not exit"
fi

kill -9 ${PID}

if [ $? -eq 0 ];then
    echo "kill $input1 success"
else
    echo "kill $input1 fail"
fi

done


