#!/usr/bin/env python3
"""Génère un jeu de données SYNTHÉTIQUE (aucun patient réel) au format du Sheet, + les valeurs attendues (calculées indépendamment)."""
import json, random, datetime as dt, sys, collections
random.seed(42)
H = ["Cas","Date cousp","NOM PRENOMS","Adresse ","SE Date début de signes","Contact","Region","District","Lieu de notification (Hopital, CSB, PFD, SDSP, autres)","Nom Commune","SE Notification","Date de notification","Classification","Conclusion 1","Date de diagnostic","SE LABO","Age (année)","date de naissance","tranche_d_age","Age (mois)","Sexe","Sexual Orientation (hétérosexuel, homosexuel, autre)","Fonction","Enceinte Non , Oui)","Si oui, SA","Immunodeprimé(Oui, NON)","Statut VIH( confirmé, Negatif, NON RENSEIGNE)","Cellules CD4","Vaccin MPOX","Si oui, Date de vaccination","Symptomes( Déf de cas)","Date de début des signes","Taux de détection","Principales manifestations cliniques","Date d'apparition des éruptions","Autres manifestation( Autre que déf de cas)","IST Non VIH","Traitements reçus","Hospitalisation","PEC Intensive","Evolution","Décès oui/non","Date de décès","Lien épidémiologique (Oui, Non)","Exposition","Exposition en détail","Voyage 21j (Oui, Non)","Notion de voyage à Madagascar (autres zones)","Notion de voyage à l'extérieur (régional)","Contact avec un animal(chien,chat,….)","Mode de Transmission (Direct, Indirect, vertical, autre)","Prélèvement (Oui ou Non)","Date de prélèvement","Laboratoire (IPM ou LA2M)","Type de prélèvement( sang, naso pharyngé, croute","Remarques","Methode de test laboratoire","Autre prélèvement","Charactères génomiques","Clade","Numéro labo","Commentaires","Colonne1","Statut_Import ","DHIS2_TEI_ID","OrgUnit_ID"]
ix = {h:i for i,h in enumerate(H)}
D = [("Analamanga","Ambohidratrimo"),("Analamanga","Antananarivo-Nord"),("Analamanga","Ankazobe"),("Boeny","Mahajanga I"),("Atsinanana","Toamasina I"),("Diana","Antsiranana I"),("Sava","Sambava"),("Vakinankaratra","Antsirabe I")]
LAST = dt.date(2026,3,31)
rows=[]; exp=collections.Counter(); cases=[]
def add(d, reg, dist, status, sex, age, dead=False, hosp=False, fmt=0):
    r=[""]*len(H)
    r[ix["NOM PRENOMS"]]="PATIENT FICTIF"; r[ix["Adresse "]]="ADRESSE FICTIVE"   # ne doit JAMAIS sortir
    r[ix["Region"]]=reg; r[ix["District"]]=dist
    iso=d.isocalendar(); r[ix["SE Notification"]]=str(iso[1])
    r[ix["Date de notification"]]= [f"{d.day}/{d.month}/{d.year}", d.strftime("%d/%m/%Y"), d.isoformat(), str((d-dt.date(1899,12,30)).days)][fmt]
    cl,co={"confirmed":("Positif","Confirmé"),"suspect":("Suspect","Suspect"),"probable":("Probable","Suspect"),"negative":("Négatif",""),"trace":("Trace d'ADN Virale","Suspect")}[status]
    r[ix["Classification"]]=cl; r[ix["Conclusion 1"]]=co
    r[ix["Age (année)"]]=str(age) if age is not None else ""; r[ix["tranche_d_age"]]="3"; r[ix["Sexe"]]=sex
    r[ix["Hospitalisation"]]="Oui" if hosp else "Non"; r[ix["Evolution"]]="Décédé" if dead else "Bonne"
    r[ix["Décès oui/non"]]="Oui" if dead else "Non"; r[ix["Prélèvement (Oui ou Non)"]]="Oui" if status in("confirmed","negative") else "Non"
    r[ix["Laboratoire (IPM ou LA2M)"]]="IPM" if status in("confirmed","negative") else ""
    r[ix["Statut VIH( confirmé, Negatif, NON RENSEIGNE)"]]=random.choice(["Negatif","NON RENSEIGNE","confirmé"])
    rows.append(r); cases.append(dict(d=d,reg=reg,dist=dist,st=("suspect" if status=="trace" else status),sex=sex,age=age,dead=dead,hosp=hosp))
# bruit de fond : 1 à 3 cas/semaine/district sur 20 semaines, puis FOYER à Mahajanga I les 14 derniers jours
for w in range(20):
    for reg,dist in D:
        for _ in range(random.randint(0,2)):
            d=LAST-dt.timedelta(days=14+w*7+random.randint(0,6))
            add(d,reg,dist,random.choice(["confirmed","confirmed","suspect","probable","negative","trace"]),random.choice(["M","F"]),random.choice([None,3,12,22,31,40,58]),dead=random.random()<0.03,hosp=random.random()<0.1,fmt=random.randint(0,3))
for _ in range(9):
    add(LAST-dt.timedelta(days=random.randint(0,12)),"Boeny","Mahajanga I","confirmed",random.choice(["M","F"]),random.choice([8,22,33]),dead=_==0,hosp=_%3==0,fmt=random.randint(0,2))
rows.append([""]*len(H))   # ligne vide
rows.append(["x"]+[""]*(len(H)-1)); rows[-1][ix["Date de notification"]]="pas une date"; rows[-1][ix["District"]]="Sambava"
json.dump({"headers":H,"rows":rows},open(sys.argv[1],"w"),ensure_ascii=False)
# --- attendus calculés indépendamment (Python) ---
tot=len(cases); c=collections.Counter(x["st"] for x in cases)
last=max(x["d"] for x in cases); r7=sum(1 for x in cases if last-dt.timedelta(days=6)<=x["d"]<=last); p7=sum(1 for x in cases if last-dt.timedelta(days=13)<=x["d"]<last-dt.timedelta(days=6))
rec=[x for x in cases if last-dt.timedelta(days=13)<=x["d"]<=last and x["dist"]=="Mahajanga I"]
json.dump({"parsed_total":tot,"status":dict(c),"deaths":sum(x["dead"] for x in cases),"hospitalised":sum(x["hosp"] for x in cases),
 "last":last.isoformat(),"first":min(x["d"] for x in cases).isoformat(),"cur7":r7,"prev7":p7,"mahajanga_recent":len(rec),
 "bad_rows":1,"districts":len({x['dist'] for x in cases})},open(sys.argv[2],"w"),indent=1)
