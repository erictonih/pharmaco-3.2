Je veux que tu adapte cette projet dans une framework codeigniter voici la version (codeigniter4-framework-v4.7.2-0-gb3359be)
vous pouvez utiliser le dossier (codeigniter4-framework-b3359be)
comme tempalte pas besoin de télécharger .
Pour mieux integrer cette code dans code igniter je veux que respecte ces regle :
- on ne dois pas écrire des requette dans controller ,
- bien faire le route à proprier ,
mettez dans assets (je parle du css dans une style.css) pour etre modifiable .
- Pour la sécurité et l'arrengemet de code on dois pas utiliser le connexion à chaque base on dois simplement l'utilisé une fois et on appelle ensuite .
- utilise le pdo pour eviter l'injection sql .
- Ca dois etrement mvc :
- ca veut dire qu'on dois utiliser un model etc ..
NB :Faite en sorte que ca sort le meme resultat comparant la code initiale là la code en desordre là ) faite genre le logique metier ne change mais l'ecriture de code peut etre modifié .
NB1 : resumé.md par de cette projet mais vous pouvez faire votre propre analyse sur cette projet .
et module.md est pour le model de structure de code .
on peut directement ensuite tester avec le php -S localhost:8000 -t public apres pour la teste de login etc ... 