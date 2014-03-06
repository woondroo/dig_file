# opkg install kmod-input-gpio-keys-polled
#/bin/bash
let "i=0"
if [ ! -d /sys/class/gpio/gpio$i ]; then #init gpio
 echo $i > /sys/class/gpio/export
fi

echo out > /sys/class/gpio/gpio$i/direction  #set gpio as output
echo 0 > /sys/class/gpio/gpio$i/value #set gpio as 0

while :
do
     echo 0 > /sys/class/gpio/gpio$i/value #all zero
     sleep 2
     echo 1 > /sys/class/gpio/gpio$i/value #gpio on
     sleep 2
done

