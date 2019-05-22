# Setting up the Cron Job

## On Kindle

After Kite and usbnetwork has been installed, go to debug mode to run private commands, so, on the Home screen, bring up the search bar (the keyboard key on a K4), and enter:
```
;debugOn
```

And now we can enable usbnet:
```
~usbNetwork
```

If you don't need to enter any more private commands, switch debug off:
```
;debugOff
```

## On Host PC (Linux box)
```
HOST_IP   = 192.168.15.201 
KINDLE_IP = 192.168.15.244
```

Get the device name that was renamed from usb0. Below example is enp0s4f1u3i1
dmesg | grep -iC 3 "usb0"

Set HOST_IP
```
sudo ifconfig enp0s4f1u3i1 192.168.15.201
```

ssh into Kindle
```
ssh root@192.168.15.244
```

In ssh session verify that cron contains entry for Display_Weather:
cat /etc/crontab/root

This should output all the cron jobs that looks like this:
```
*/15 * * * * /usr/sbin/checkpmond
*/15 * * * * /usr/sbin/tinyrot
*/60 * * * * /usr/sbin/loginfo tmpfs
*/60 * * * * /usr/sbin/loginfo localVars
*/60 * * * * /usr/sbin/loginfo memusedump
*/15 * * * * /usr/sbin/loginfo powerdcheck
```

If root doesn't contain Display_Weather or it's binary or corrupt edit backup file:
nano /etc/crontab/root.bak

Add and save as root
```
*/30 * * * * /mnt/us/Display_Weather
```

```
cat /etc/crontab/root
*/15 * * * * /usr/sbin/checkpmond
*/15 * * * * /usr/sbin/tinyrot
*/60 * * * * /usr/sbin/loginfo tmpfs
*/60 * * * * /usr/sbin/loginfo localVars
*/60 * * * * /usr/sbin/loginfo memusedump
*/15 * * * * /usr/sbin/loginfo powerdcheck
*/30 * * * * /mnt/us/Display_Weather

```
