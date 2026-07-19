# MikroTik — checklist

## Hotspot

```routeros
/radius add service=hotspot address=<VPS_VPN> secret="<SECRET>" authentication-port=1812 accounting-port=1813 timeout=1s
/ip hotspot profile set [find name="hsprof1"] use-radius=yes radius-accounting=yes radius-interim-update=5m
/radius incoming set accept=yes port=3799
```

## PPPoE

```routeros
/radius set [find address=<VPS_VPN>] service=hotspot,ppp
/ppp aaa set use-radius=yes accounting=yes interim-update=5m
```

## Validação

```routeros
/radius monitor [find] once
/ip hotspot active print detail
/ppp active print detail
```

O cliente autenticado por RADIUS aparece com flag `R`.
