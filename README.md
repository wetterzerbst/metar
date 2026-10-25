metar_eduz: Dieser Code wurde nach Vorgabe des METAR-Handbuches DWD erstellt. Dabei wird die Taupunktberechnung vor der Ausgabe des METAR (Kommastellen werden abgeschnitten und es wird gerundet) nach MAGNUS berechnet.
Dieses Beispiel soll die METAR-Generierung mit einer Wetterstation der Firma ECOWITT ermöglichen. Dabei wird die Wolkenuntergrenze und Sichtweite bewusst weg gelassen, da hierfür keine Messintrumente vorhanden sind. Dennoch ist der METAR gesetzeskonform so gehalten, das man sich das Kondensationsniveau selbst ausrechnen kann, aber trotzdem keine erfundenen Werte generiert werden.

metar_core.php: Dieser Code beinhaltet die Berechnung der Wolkenuntergrenze, Regenmenge anhand der Ausgabe durch die Wetterstation und die Sichtweite.
zum metar_core.php gehören die view.php und die metar.php
