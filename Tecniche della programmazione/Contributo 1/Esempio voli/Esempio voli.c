/*

Gestione di una tabella di voli 

un volo e` caratterizzato da
- codice (5 caratteri alfanumerici)
- destinazione (stringa di caratteri)
- ora di partenza (ore, minuti: due interi)
- numero di posti attualmente liberi

la gestione di una tabella di voli consiste nella possibilita` di
- stampare un volo della tabella, caratterizzato da un certo codice
- stampare i voli della tabella
- aggiungere un volo alla tabella,
- eliminare un volo avente caratterizzato da un certo codice
- modificare l'ora di partenza di un volo caratterizzato
  da un certo codice
- prenotare posti in un volo caratterizzato da un
  certo codice (cioe` modificarne il numero di posti liberi)
- memorizzare i dati di una tabella di voli in un file di voli
- caricare nella tabella dati da un file di voli

Si vede che in questa tabella il campo codice e` la "chiave di ricerca" per i voli memorizzati.

*/

#include<stdio.h>
#include<string.h>
#include<stdlib.h>

#define MAXVOLI 5
#define MAXLUNG 50                    /* per i buffer con cui leggere stringhe */
#define NOMEFILEAPPOGGIO "TEMP.DAT"   /* usato nei momenti critici             */
#define RATIO 30


/* definizione delle strutture dati */

struct ora {
   int ore, minuti;
};

struct volo {
   char codice[6];
   char * destinazione;
   struct ora oraPartenza;
   int postiLiberi;
};

typedef struct volo TipoVolo;

typedef                    /* ora arrayVoli e` un array dinamico */
   struct {
     TipoVolo * arrayVoli;
     int quantiVoli;
     int voliAllocati;     /* quanti voli sono stati allocati nell'array dinamico */
   }
   TipoTabella;



/* prototipi di funzioni */
/* quelle cambiate rispetto a VOLI5.C, hanno un 2 finale nel nome */

int indiceVolo(TipoTabella t, char cod[]);
void stampaQuelVolo(TipoTabella t, char cod[]);
void stampaTabella(TipoTabella t);
void stampaVolo(TipoVolo v);
int eliminaVolo(TipoTabella *t, char cod[]);
int cambiaOraPartenza(TipoTabella *t, char cod[], int nuovaOra, int nuoviMinuti);
int cambiaPostiLiberi(TipoTabella *t, char cod[], int k);
void daTabellaInFile(TipoTabella t, char *nmf);

int aggiungiVolo2(TipoTabella *t);
int daFileInTabella2(char *nmf, TipoTabella *t, double extraDim);


int main() {
  TipoTabella tabVoli;
  TipoVolo unVolo;
  int riuscita,o,m,pl,
      scelta;  /* scelta nel menu' */
  char buffer[MAXLUNG];

   tabVoli.arrayVoli = malloc(MAXVOLI*sizeof(TipoVolo)); /* prima allocazione tabella voli */
   if (tabVoli.arrayVoli==NULL) {
     printf("PROBLEMI SUBITO IN ALLOCAZIONE ! ciao.\n");
     return 0;
   }
   /* se non abbiamo avuto problmei all'inizio, possiamo andare avanti
   con l'inizializzazione della tabella */
   tabVoli.quantiVoli=0;           /* inizializzazione del numero di  voli */
   tabVoli.voliAllocati=MAXVOLI;   /* inizializzazione del numero di struct allocate */

   do {
     printf(" -      scegli                         -\n");
     printf(" - stampa dei voli                 (1) -\n");
     printf(" - stampa di un certo volo         (2) -\n");
     printf(" - aggiunta di un volo             (3) -\n");
     printf(" - eliminazione di un volo         (4) -\n");
     printf(" - modifica ora partenza           (5) -\n");
     printf(" - prenotazione posti              (6) -\n");
     printf(" - tabella --> filedati            (7) -\n");
     printf(" - filedati --> tabella            (8) -\n");
     printf(" - fine                            (0) -\n");

     scanf("%d", &scelta);

     switch (scelta) {
       case 1:
      printf(" --- %d voli in tabella:\n", tabVoli.quantiVoli);
      stampaTabella(tabVoli);
      break;
       case 2:
      printf(" --- codice volo? ");
      scanf("%s", buffer);
      stampaQuelVolo(tabVoli, buffer);
      putchar('\n');
      break;
       case 3:
      riuscita=aggiungiVolo(&tabVoli);
      if(!riuscita)
    printf(" --- aggiunta non effettuata -\n");
      else
    printf(" --- fatto -\n");
      break;
       case 4:
      printf(" --- codice volo da eliminare? ");
      scanf("%s", buffer);

      riuscita=eliminaVolo(&tabVoli, buffer);
      if(!riuscita)
    printf(" --- eliminazione non effettuata -\n");
      else
    printf(" --- fatto -\n");
      break;
       case 5:
      printf(" --- codice e nuova ora (scrivi ore e minuti) ");
      scanf("%s %d %d", buffer, &o, &m);

      riuscita=cambiaOraPartenza(&tabVoli, buffer,o,m);
      if(!riuscita)
    printf(" --- modifica non effettuata -\n");
      else
    printf(" --- fatto -\n");
      break;
       case 6:
      printf(" --- codice e quanti posti ");
      scanf("%s %d", buffer, &pl);

      riuscita=cambiaPostiLiberi(&tabVoli, buffer, pl);
      if(!riuscita)
    printf(" --- modifica non effettuata -\n");
      else
    printf(" --- fatto -\n");
      break;
       case 7:
      printf(" --- nome del file in cui salvare la tabella: ");
      scanf("%s", buffer);

      daTabellaInFile(tabVoli, buffer);
      break;
       case 8:
      printf(" --- nome del file da cui caricare i dati nella tabella: ");
      scanf("%s", buffer);

      riuscita=daFileInTabella2(buffer, &tabVoli, RATIO);
      if(!riuscita)
    printf(" --- problemi ! -\n");
      else
    printf(" --- fatto -\n");
      break;
       case 0:
      printf(" - USCITA DAL PROGRAMMA\n");
      break;
       default:
      printf(" - opzione sballata\n");
     } /* fine switch */
   } while (scelta!=0);       /* fine do_while*/

  printf("\nFINE\n");
  return 0;
  }



/* FUNZIONI  FUNZIONI  FUNZIONI  FUNZIONI  FUNZIONI  FUNZIONI  FUNZIONI  */

/* funzione di ricerca */
int indiceVolo(TipoTabella t, char cod[]) {
  int trovato=0, i=0;
  while (!trovato && (i<t.quantiVoli))
    if (strcmp(t.arrayVoli[i].codice, cod)==0)
      trovato=1;
    else i++;
  /* all'uscita dal ciclo, se abbiamo trovato il volo i
  ne contiene l'indice */

  if (trovato)
    return i;
  else return -1;
}


/* stampa di un volo passato come argomento */
void stampaVolo(TipoVolo v) {
  printf("...VOLO %s (%d disponibili), partenza alle %2d:%2d per %s",
  v.codice, v.postiLiberi, v.oraPartenza.ore, v.oraPartenza.minuti, v.destinazione);
return;
}


/* stampa di un volo della tabella, identificato dal secondo parametro (codice) */
void stampaQuelVolo(TipoTabella t, char cod[]) {
  int k=indiceVolo(t, cod);

  if (k==-1)
    printf("\n volo %s non in tabella -\n", cod);
  else stampaVolo(t.arrayVoli[k]);
return;
}


/* stampa di tutti i voli in tabella */
void stampaTabella(TipoTabella t) {
  int i;
  printf("\nStampa intera tabella voli\n");

  for (i=0; i<t.quantiVoli; i++) {
    stampaVolo(t.arrayVoli[i]);
    printf("\n");
  }
return;
}


/* aggiunta di un volo nella tabella 
   ************************************************************************
   QUI abbiamo fatto qualche cambiamento rispetto a quanto visto a lezione: 
   ora c'e' un solo paramtero - la tabella - e l'interazione con l'utente 
   produce i dati da inserire 
   ************************************************************************   
   */
   
int aggiungiVolo(TipoTabella *t) {
  char buffer[MAXLUNG], *aux;
  int o,m,pl;

  if (t->quantiVoli==t->voliAllocati) {
    printf("\n spazio insufficiente: SI PROVVEDE ALLA ESTENSIONE ... \n");
       /* salvataggio su file di appoggio */
    daTabellaInFile(*t, NOMEFILEAPPOGGIO);
       /* ricaricamento */
    pl=daFileInTabella2(NOMEFILEAPPOGGIO, t, RATIO);

    if(pl==0) {
      printf ("--- problemi ! -\n");
      return 0;
    }
  }

  /* se non siamo usciti dalla funzione, siamo pronti per l'aggiunta */
    printf(" - codice? ");
    scanf("%s", t->arrayVoli[t->quantiVoli].codice);

    printf(" - destinazione? ");
    scanf("%s", buffer);
    aux=malloc(strlen(buffer)+1);
    if (aux==NULL) {
      printf ("\nPROBLEMI IN ALLOCAZIONE\n");
      return 0;
    }
    else {
      strcpy(aux, buffer);
      t->arrayVoli[t->quantiVoli].destinazione=aux;
    }

    printf(" - ora della partenza (scrivi ore e minuti)? ");
    scanf("%d %d", &o, &m);
    t->arrayVoli[t->quantiVoli].oraPartenza.ore=o;
    t->arrayVoli[t->quantiVoli].oraPartenza.minuti=m;

    printf(" - posti disponibili? ");
    scanf("%d", &pl);
    t->arrayVoli[t->quantiVoli].postiLiberi=pl;

    /* l'aggiunta ha avuto successo */
    (t->quantiVoli)+=1;

return 1;
}

/* eliminazione di un volo (mediante copia dell'ultimo volo su quello da eliminare */
int eliminaVolo(TipoTabella *t, char cod[]) {
  int k, ultimo;

  k=indiceVolo(*t, cod);   /* indice elemento da eliminare (o -1) */
  ultimo=t->quantiVoli-1;  /* indice ultimo elemento in tabella */

  if (k==-1) {
    printf("\n volo %s non in tabella -\n", cod);
    return 0;
  }
  else {
    t->arrayVoli[k] = t->arrayVoli[ultimo];
    t->quantiVoli-=1;
    return 1;
  }
}


/* cambia un dato nel volo di codice cod della tabella *t
restituisce 0 o 1 a seconda del successo dell'operazione
Si cerca l'elemento da eliminare e lo si modifica ...  */
int cambiaOraPartenza(TipoTabella *t, char cod[], int nuovaOra, int nuoviMinuti) {
  int k;

  k=indiceVolo(*t, cod);   /* indice elemento da eliminare (o -1) */

  if (k==-1) {
    printf("\n volo %s non in tabella -\n", cod);
    return 0;
  }
  else {
    t->arrayVoli[k].oraPartenza.ore = nuovaOra;
    t->arrayVoli[k].oraPartenza.minuti = nuoviMinuti;
    return 1;
  }
}

/* cambia un dato nel volo di codice cod della tabella *t
restituisce 0 o 1 a seconda del successo dell'operazione
Si cerca l'elemento da eliminare e lo si modifica ...

diminuisce di k i posti liberi (quindi li aumenta se k e` negativo);
restituisce 0 o 1 a seconda del successo dell'operazione */
int cambiaPostiLiberi(TipoTabella *t, char cod[], int num) {
  int h;

  h=indiceVolo(*t, cod);   /* indice elemento da eliminare (o -1) */

  if (h==-1) {
    printf("\n volo %s non in tabella -\n", cod);
    return 0;
  }
  else
    if(num<=t->arrayVoli[h].postiLiberi) {
      t->arrayVoli[h].postiLiberi -= num;
      return 1;
    }
    else  {
      printf("\n posti insufficienti -\n");
      return 0;
    }
}



/* scarica in nmf i dati di tabvoli;
nmf e` un file di testo in cui
- la prima linea contiene il numero di voli della tabella
- le successive righe contengono i dati sui voli in tabella,
  un volo per linea, secondo la seguente disposizione:
  codice destinazione ora-partenza minuti-partenza posti-liberi
  */
void daTabellaInFile (TipoTabella t, char *nmf) {
  FILE *f;
  int i;

  f=fopen(nmf, "w");
  if (f==NULL)
    printf("\nPROBLEMI IN APERTURA FILE\ndati non salvati!!!\n");
  else {
    fprintf(f, "%d\n", t.quantiVoli);  /* quantiVoli, in prima linea */

    for(i=0; i<t.quantiVoli; i++){          /* un volo per riga */
      fprintf(f, "%s ", t.arrayVoli[i].codice);
      fprintf(f, "%s ", t.arrayVoli[i].destinazione);
      fprintf(f, "%d ", t.arrayVoli[i].oraPartenza.ore);
      fprintf(f, "%d ", t.arrayVoli[i].oraPartenza.minuti);
      fprintf(f, "%d\n", t.arrayVoli[i].postiLiberi);
    }
    printf("\nfatto.\n");
    fclose(f);
  }  /* fine else */
return;
}


/* *****************************************************
    CAMBIATA
  viene allocato un extraDIM% in piu` dello spazio necessario a contenere
  quantiVoli voli

  la funzione restituisce 0 se ci sono stati problemi o 1 in caso di successo
 */
int daFileInTabella2 (char *nmf, TipoTabella *t, double extraDim) {
  FILE *f;
  int i;
  char buf[MAXLUNG];

  f=fopen(nmf, "r");   /* apertura in lettura */
  if (f==NULL) {
    printf("\nPROBLEMI IN APERTURA FILE!!!\n");
    return 0;
  }

  /* se stiamo qui l'apertura del file ha avuto successo */
  fscanf(f, "%d\n", &(t->quantiVoli));          /* lettura quantiVoli */

     /* deallocazione di arrayVoli e sua riallocazione opportuna
      NB avremmo potuto usare bene realloc qui ...) */
  free(t->arrayVoli);
  t->arrayVoli=malloc((int)((1+extraDim/100)*t->quantiVoli)*sizeof(TipoVolo));
  if(t->arrayVoli==NULL) {
    printf("\nPROBLEMI IN ALLOCAZIONE MEMORIA!!!\n");
    fclose(f);
    return 0;
  }

  /* se stiamo qui anche l'allocazione di arrayVoli ha avuto successo */

  t->voliAllocati=(int)((1+extraDim/100)*t->quantiVoli);  /* cosi' sappiamo quanti voi sono
              stati allocati */

    for(i=0; i<t->quantiVoli; i++) {           /* un volo per riga */
      fscanf(f, "%s ", t->arrayVoli[i].codice);     /* lettura codice */

      fscanf(f, "%s ", buf);                        /* destinazione */
      t->arrayVoli[i].destinazione=malloc(strlen(buf)+1);
      if (t->arrayVoli[i].destinazione==NULL) {
    printf("\nPROBLEMI IN ALLOCAZIONE STRINGA %s\n", buf);
    return 0;
      }
      else strcpy(t->arrayVoli[i].destinazione, buf);

      fscanf(f, "%d ", &(t->arrayVoli[i].oraPartenza.ore));  /* oraPartenza */
      fscanf(f, "%d ", &(t->arrayVoli[i].oraPartenza.minuti));
          /* posti */
      fscanf(f, "%d\n", &(t->arrayVoli[i].postiLiberi));
    }
    printf("\nfinito.\n");
    fclose(f);

return 1;
}
